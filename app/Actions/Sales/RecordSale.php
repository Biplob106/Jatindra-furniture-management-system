<?php

namespace App\Actions\Sales;

use App\Enums\CashPaymentMethod;
use App\Enums\StockMovementType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
use App\Services\CashService;
use App\Services\NumberSeries;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sells readymade stock over the counter.
 *
 * Section 9, and the retail twin of the credit purchase:
 *
 *   Cash sale   -> sales, stock_movements | none | transactions in
 *   Credit sale -> sales, stock_movements | sale due_amount | NOTHING
 *
 * A credit sale must not write a transactions row. The almirah left the floor
 * and the customer owes for it, but no note came into the drawer, and a row
 * saying otherwise makes the nightly closing wrong.
 *
 * There is no customer ledger table: what a customer owes lives on the sale's
 * own due_amount, which is what docs/schema.md section 9 names as the party
 * ledger for this event.
 *
 * Money is never taken from the form. Line totals come from quantity and unit
 * price, the subtotal from the lines that landed, and the total from those.
 */
class RecordSale
{
    public function __construct(
        private readonly NumberSeries $numbers,
        private readonly CashService $cash,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     * @param  Customer|null  $customer  Required once anything is left owed:
     *                                   somebody has to owe it.
     * @param  Account|null  $account  Where the money landed. Required only
     *                                 when something was actually paid.
     */
    public function handle(
        array $data,
        array $items,
        ?Customer $customer = null,
        ?Account $account = null,
        ?int $userId = null,
    ): Sale {
        if ($items === []) {
            throw new InvalidArgumentException('A sale needs at least one line.');
        }

        $saleDate = (string) $data['sale_date'];
        $lines = $this->priceLines($items);

        $subtotal = $this->sumOf($lines);
        $discount = $this->money($data['discount'] ?? '0');
        $delivery = $this->money($data['delivery_charge'] ?? '0');
        $total = bcsub(bcadd($subtotal, $delivery, 2), $discount, 2);

        if (bccomp($total, '0.00', 2) < 0) {
            throw new InvalidArgumentException('A discount cannot be larger than the invoice.');
        }

        $paid = $this->resolvePaid($data, $total);
        $due = bcsub($total, $paid, 2);

        if (bccomp($paid, '0.00', 2) > 0 && $account === null) {
            throw new InvalidArgumentException('Money cannot be taken without an account for it to land in.');
        }

        // A walk-in can pay cash and leave. A walk-in cannot owe: there would
        // be nobody to ask for it.
        if (bccomp($due, '0.00', 2) > 0 && $customer === null) {
            throw new InvalidArgumentException('A sale left owing needs a customer who owes it.');
        }

        return DB::transaction(function () use (
            $data, $lines, $customer, $account, $userId, $saleDate, $subtotal, $discount, $delivery, $total, $paid, $due
        ) {
            $sale = (new Sale)->forceFill([
                'invoice_no' => $this->numbers->issue(NumberSeries::SALE, $saleDate),
                'customer_id' => $customer?->id,
                'customer_name' => $data['customer_name'] ?? $customer?->name,
                'customer_phone' => $data['customer_phone'] ?? $customer?->phone,
                'shop_id' => $data['shop_id'],
                'sale_date' => $saleDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_charge' => $delivery,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            $sale->save();

            foreach ($lines as $line) {
                $this->recordLine($sale, $line, $saleDate, $userId);
            }

            // Only if money actually came in. A credit sale stops here, and
            // that is the whole point of it.
            if (bccomp($paid, '0.00', 2) > 0) {
                $this->cash->record(
                    account: $account,
                    direction: TransactionDirection::In,
                    amount: $paid,
                    txnDate: $saleDate,
                    source: TransactionSource::Sale,
                    party: $customer,
                    sourceId: $sale->id,
                    paymentMethod: $this->methodFrom($data),
                    note: $sale->invoice_no,
                    createdBy: $userId,
                    shopId: $sale->shop_id,
                );
            }

            return $sale->refresh();
        });
    }

    /**
     * One invoice line: the row, the stock leaving, and the floor count it
     * moves.
     *
     * @param  array<string, mixed>  $line
     */
    private function recordLine(Sale $sale, array $line, string $saleDate, ?int $userId): void
    {
        // Locked before its stock is read, so two counters selling the last
        // almirah at once cannot both succeed.
        $product = Product::whereKey($line['product_id'])->lockForUpdate()->firstOrFail();

        $remaining = bcsub((string) $product->current_stock, $line['quantity'], 2);

        if (bccomp($remaining, '0.00', 2) < 0) {
            throw new RuntimeException(
                "{$product->name}: দোকানে যত আছে তার বেশি বিক্রি করা যাবে না। এখন আছে {$product->current_stock} টি।"
            );
        }

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $line['quantity'],
            'unit_price' => $line['unit_price'],
            'line_total' => $line['line_total'],
        ]);

        // Costed at what the piece cost to make or buy, not what it sold for.
        // The difference between the two is the margin, and mixing them would
        // hide it.
        StockMovement::create([
            'product_id' => $product->id,
            'shop_id' => $sale->shop_id,
            'movement_date' => $saleDate,
            'type' => StockMovementType::SaleOut,
            'quantity' => $line['quantity'],
            'unit_cost' => $product->cost_price,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'note' => $sale->invoice_no,
            'created_by' => $userId,
        ]);

        $product->forceFill(['current_stock' => $remaining])->save();
    }

    /**
     * Prices every line. A unit price left out falls back to the product's own
     * sale price, which is the common counter case; a price sent as a line
     * total is ignored.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function priceLines(array $items): array
    {
        $products = Product::query()
            ->whereIn('id', array_column($items, 'product_id'))
            ->get()
            ->keyBy('id');

        return array_map(function (array $row) use ($products) {
            $product = $products[(int) $row['product_id']] ?? null;

            if ($product === null) {
                throw new InvalidArgumentException("Unknown product on the invoice: {$row['product_id']}");
            }

            $quantity = $this->quantity($row['quantity'] ?? 0);
            $unitPrice = $this->money($row['unit_price'] ?? $product->sale_price);

            if (bccomp($quantity, '0.00', 2) <= 0) {
                throw new InvalidArgumentException('An invoice line sells a positive quantity.');
            }

            if (bccomp($unitPrice, '0.00', 2) < 0) {
                throw new InvalidArgumentException('A unit price is never negative.');
            }

            return [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($quantity, $unitPrice, 2),
            ];
        }, array_values($items));
    }

    /**
     * How much was handed over. Nothing said means the whole invoice, which is
     * what a counter sale usually is.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolvePaid(array $data, string $total): string
    {
        if (! array_key_exists('paid_amount', $data) || $data['paid_amount'] === null) {
            return $total;
        }

        $paid = $this->money($data['paid_amount']);

        if (bccomp($paid, '0.00', 2) < 0) {
            throw new InvalidArgumentException('A payment is never negative.');
        }

        if (bccomp($paid, $total, 2) > 0) {
            throw new InvalidArgumentException("Taking {$paid} against an invoice of {$total} would leave change nobody recorded.");
        }

        return $paid;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function methodFrom(array $data): CashPaymentMethod
    {
        return match (true) {
            ! isset($data['payment_method']) => CashPaymentMethod::Cash,
            $data['payment_method'] instanceof CashPaymentMethod => $data['payment_method'],
            default => CashPaymentMethod::from($data['payment_method']),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function sumOf(array $lines): string
    {
        return array_reduce(
            $lines,
            fn (string $carry, array $line) => bcadd($carry, $line['line_total'], 2),
            '0.00'
        );
    }

    private function quantity(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
