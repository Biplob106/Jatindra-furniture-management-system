<?php

namespace App\Actions\Purchases;

use App\Enums\CashPaymentMethod;
use App\Enums\MaterialMovementType;
use App\Enums\MaterialUnit;
use App\Enums\PurchaseItemType;
use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\CashService;
use App\Services\LedgerService;
use App\Services\NumberSeries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a supplier challan: the goods in, what we now owe, and the money
 * only if any actually left.
 *
 * This is the case the three-ledger design exists to protect. Section 9 of
 * docs/schema.md:
 *
 *   Cash purchase   -> purchases, material_movements | supplier_ledger credit
 *                      and debit | transactions out
 *   Credit purchase -> purchases, material_movements | supplier_ledger credit
 *                      | NOTHING
 *
 * A credit purchase must never write a transactions row. The stock arrived and
 * the debt is real, but no note left the drawer, and a row saying otherwise
 * makes the nightly closing wrong for as long as nobody notices.
 *
 * The ledger carries a credit for the full amount either way. A cash purchase
 * then carries the matching debit, so the supplier's history reads as a
 * challan and a payment rather than as nothing having happened.
 *
 * Money is never taken from the form. Line totals are computed from quantity
 * and unit price, the subtotal is summed from the lines that landed, and the
 * total follows from those.
 */
class RecordPurchase
{
    public function __construct(
        private readonly NumberSeries $numbers,
        private readonly LedgerService $ledger,
        private readonly CashService $cash,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items  One row per challan line.
     * @param  Account|null  $account  Where the money leaves from. Required
     *                                 only when something is actually paid.
     */
    public function handle(
        array $data,
        array $items,
        Supplier $supplier,
        ?Account $account = null,
        ?int $userId = null,
    ): Purchase {
        if ($items === []) {
            throw new InvalidArgumentException('A purchase needs at least one line.');
        }

        $paymentType = $data['payment_type'] instanceof PurchasePaymentType
            ? $data['payment_type']
            : PurchasePaymentType::from($data['payment_type']);

        $purchaseDate = (string) $data['purchase_date'];
        $lines = $this->priceLines($items);

        $subtotal = $this->sumOf($lines, 'line_total');
        $transport = $this->money($data['transport_cost'] ?? '0');
        $discount = $this->money($data['discount'] ?? '0');
        $total = bcsub(bcadd($subtotal, $transport, 2), $discount, 2);

        if (bccomp($total, '0.00', 2) < 0) {
            throw new InvalidArgumentException('A discount cannot be larger than the challan.');
        }

        $paid = $this->resolvePaid($paymentType, $total, $data['paid_amount'] ?? null);

        if (bccomp($paid, '0.00', 2) > 0 && $account === null) {
            throw new InvalidArgumentException('Money cannot be paid without an account for it to leave.');
        }

        return DB::transaction(function () use (
            $data, $lines, $supplier, $account, $userId,
            $paymentType, $purchaseDate, $subtotal, $transport, $discount, $total, $paid
        ) {
            $due = bcsub($total, $paid, 2);

            // forceFill throughout: purchase_no, the paid and due figures and
            // the status are all guarded, because they follow from the
            // arithmetic above and must never arrive from a form.
            $purchase = (new Purchase)->forceFill([
                'purchase_no' => $this->numbers->issue(NumberSeries::PURCHASE, $purchaseDate),
                'supplier_id' => $supplier->id,
                'shop_id' => $data['shop_id'] ?? null,
                'purchase_date' => $purchaseDate,
                'reference_no' => $data['reference_no'] ?? null,
                'payment_type' => $paymentType,
                'payment_due_date' => $this->resolveDueDate($data, $supplier, $purchaseDate, $due),
                'subtotal' => $subtotal,
                'transport_cost' => $transport,
                'discount' => $discount,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'status' => $this->statusFor($paid, $due),
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            $purchase->save();

            foreach ($lines as $line) {
                $this->recordLine($purchase, $line, $purchaseDate, $userId);
            }

            // Write 2: what we now owe. Always, whoever paid and however.
            $this->ledger->recordSupplier(
                supplier: $supplier,
                type: SupplierLedgerEntryType::Purchase,
                amount: $total,
                entryDate: $purchaseDate,
                reference: $purchase,
                note: $purchase->purchase_no,
                createdBy: $userId,
            );

            // Write 3: only if money actually moved. A credit purchase stops
            // at the line above and that is the whole point.
            if (bccomp($paid, '0.00', 2) > 0) {
                $this->settle($purchase, $supplier, $account, $paid, $purchaseDate, $data, $userId);
            }

            return $purchase->refresh();
        });
    }

    /**
     * The money leaving, and the ledger debit that matches it.
     *
     * withdraw, not record: a cash box cannot go below nothing, and a refusal
     * takes the whole challan with it rather than leaving stock that was never
     * paid for.
     *
     * @param  array<string, mixed>  $data
     */
    private function settle(
        Purchase $purchase,
        Supplier $supplier,
        Account $account,
        string $paid,
        string $purchaseDate,
        array $data,
        ?int $userId,
    ): void {
        $this->ledger->recordSupplier(
            supplier: $supplier,
            type: SupplierLedgerEntryType::Payment,
            amount: $paid,
            entryDate: $purchaseDate,
            reference: $purchase,
            note: $purchase->purchase_no,
            createdBy: $userId,
        );

        $this->cash->withdraw(
            account: $account,
            amount: $paid,
            txnDate: $purchaseDate,
            source: TransactionSource::PurchasePayment,
            party: $supplier,
            sourceId: $purchase->id,
            paymentMethod: $this->methodFrom($data),
            note: $purchase->purchase_no,
            createdBy: $userId,
            shopId: $purchase->shop_id,
        );
    }

    /**
     * One challan line: the row, the stock movement, and the running stock and
     * average cost it moves.
     *
     * @param  array<string, mixed>  $line
     */
    private function recordLine(Purchase $purchase, array $line, string $purchaseDate, ?int $userId): void
    {
        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'item_type' => $line['item_type'],
            'item_id' => $line['item_id'],
            'quantity' => $line['quantity'],
            'unit' => $line['unit'],
            'unit_price' => $line['unit_price'],
            'line_total' => $line['line_total'],
            'note' => $line['note'],
        ]);

        // Products arrive in phase 6 with their own stock_movements table.
        if ($line['item_type'] !== PurchaseItemType::Material) {
            return;
        }

        MaterialMovement::create([
            'material_id' => $line['item_id'],
            'movement_date' => $purchaseDate,
            'type' => MaterialMovementType::In,
            'quantity' => $line['quantity'],
            'unit_cost' => $line['unit_price'],
            'reference_type' => Purchase::class,
            'reference_id' => $purchase->id,
            'note' => $purchase->purchase_no,
            'created_by' => $userId,
        ]);

        $this->restock($line['item_id'], $line['quantity'], $line['unit_price']);
    }

    /**
     * Moves a material's stock on hand and its weighted average cost.
     *
     * The row is locked first, so two challans landing at once cannot both
     * read the same starting stock and lose one of the two arrivals.
     */
    private function restock(int $materialId, string $quantity, string $unitPrice): void
    {
        $material = Material::whereKey($materialId)->lockForUpdate()->firstOrFail();

        $oldStock = (string) $material->current_stock;
        $newStock = bcadd($oldStock, $quantity, 3);

        // Weighted average: what is already on the floor, plus what just
        // arrived, over the total. A shop that let stock go negative gets the
        // new price rather than a division by zero.
        $avgCost = bccomp($newStock, '0.000', 3) > 0
            ? bcdiv(
                bcadd(
                    bcmul($oldStock, (string) $material->avg_cost, 4),
                    bcmul($quantity, $unitPrice, 4),
                    4
                ),
                $newStock,
                2
            )
            : $unitPrice;

        $material->forceFill([
            'current_stock' => $newStock,
            'avg_cost' => $avgCost,
        ])->save();
    }

    /**
     * Prices every line from quantity and unit price. A client sending its own
     * line_total is ignored.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function priceLines(array $items): array
    {
        return array_map(function (array $row) {
            $type = ($row['item_type'] ?? PurchaseItemType::Material) instanceof PurchaseItemType
                ? $row['item_type']
                : PurchaseItemType::from($row['item_type']);

            if ($type === PurchaseItemType::Product) {
                throw new InvalidArgumentException('Readymade product lines need the products table, which phase 6 adds.');
            }

            $quantity = number_format((float) ($row['quantity'] ?? 0), 3, '.', '');
            $unitPrice = $this->money($row['unit_price'] ?? 0);

            if (bccomp($quantity, '0.000', 3) <= 0) {
                throw new InvalidArgumentException('A challan line moves a positive quantity.');
            }

            if (bccomp($unitPrice, '0.00', 2) < 0) {
                throw new InvalidArgumentException('A unit price is never negative.');
            }

            $unit = $row['unit'] ?? null;

            return [
                'item_type' => $type,
                'item_id' => (int) $row['item_id'],
                'quantity' => $quantity,
                'unit' => $unit instanceof MaterialUnit ? $unit->value : $unit,
                'unit_price' => $unitPrice,
                'line_total' => bcmul($quantity, $unitPrice, 2),
                'note' => $row['note'] ?? null,
            ];
        }, array_values($items));
    }

    /**
     * How much of this challan was settled at the counter.
     */
    private function resolvePaid(PurchasePaymentType $type, string $total, mixed $given): string
    {
        return match ($type) {
            PurchasePaymentType::Cash => $total,
            PurchasePaymentType::Credit => '0.00',
            PurchasePaymentType::Partial => $this->assertPartial($this->money($given ?? 0), $total),
        };
    }

    /**
     * A partial payment that covers all or none of the challan is not partial:
     * it is a cash or a credit purchase being recorded under the wrong name,
     * and the payment_type column is what the payable report reads.
     */
    private function assertPartial(string $paid, string $total): string
    {
        if (bccomp($paid, '0.00', 2) <= 0 || bccomp($paid, $total, 2) >= 0) {
            throw new InvalidArgumentException(
                "A partial payment is more than nothing and less than the total: {$paid} of {$total}."
            );
        }

        return $paid;
    }

    private function statusFor(string $paid, string $due): PurchaseStatus
    {
        return match (true) {
            bccomp($due, '0.00', 2) <= 0 => PurchaseStatus::Paid,
            bccomp($paid, '0.00', 2) === 0 => PurchaseStatus::Pending,
            default => PurchaseStatus::Partial,
        };
    }

    /**
     * When the rest falls due. The supplier's agreed credit days are used when
     * the form says nothing, so the aging report has a date to measure from.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveDueDate(array $data, Supplier $supplier, string $purchaseDate, string $due): ?string
    {
        if (! empty($data['payment_due_date'])) {
            return (string) $data['payment_due_date'];
        }

        if (bccomp($due, '0.00', 2) <= 0 || $supplier->default_credit_days <= 0) {
            return null;
        }

        return CarbonImmutable::parse($purchaseDate)
            ->addDays($supplier->default_credit_days)
            ->toDateString();
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
    private function sumOf(array $lines, string $key): string
    {
        return array_reduce(
            $lines,
            fn (string $carry, array $line) => bcadd($carry, $line[$key], 2),
            '0.00'
        );
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
