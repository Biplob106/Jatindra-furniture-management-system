<?php

namespace App\Actions\Purchases;

use App\Enums\CashPaymentMethod;
use App\Enums\PartyType;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\PartyPayment;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\CashService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pays a supplier and says which challans the money settled.
 *
 * Section 9: party_payments and payment_allocations, a supplier_ledger debit,
 * and a transactions row out. Money always moves here, which is what separates
 * this from a credit purchase.
 *
 * The ledger debit is for the whole amount handed over, allocated or not. A
 * shop that pays 50,000 against 42,000 of challans has paid 50,000, and the
 * 8,000 sits on account until the next one arrives. Clamping it to what was
 * allocated would lose real money.
 *
 * Allocation defaults to oldest challan first, which is how the aging list is
 * read and how a supplier expects their book to be cleared.
 */
class PaySupplier
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CashService $cash,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>|null  $allocations  purchase id => amount.
     *                                                Null spreads the payment
     *                                                over the oldest challans.
     */
    public function handle(
        Supplier $supplier,
        Account $account,
        array $data,
        ?array $allocations = null,
        ?int $userId = null,
    ): PartyPayment {
        $amount = $this->money($data['amount'] ?? 0);
        $paymentDate = (string) $data['payment_date'];

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('A payment hands over a positive amount.');
        }

        return DB::transaction(function () use ($supplier, $account, $data, $allocations, $userId, $amount, $paymentDate) {
            // Lock the challans before reading their dues, so two payments
            // recorded at once cannot both settle the same outstanding amount.
            $open = $this->lockOpenPurchases($supplier);

            $spread = $allocations === null
                ? $this->oldestFirst($open, $amount)
                : $this->asGiven($open, $allocations, $amount);

            $payment = PartyPayment::create([
                'party_type' => PartyType::Supplier,
                'party_id' => $supplier->id,
                'direction' => TransactionDirection::Out,
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'account_id' => $account->id,
                'payment_method' => $this->methodFrom($data),
                'reference_no' => $data['reference_no'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($spread as $purchaseId => $allocated) {
                PaymentAllocation::create([
                    'party_payment_id' => $payment->id,
                    'allocatable_type' => Purchase::class,
                    'allocatable_id' => $purchaseId,
                    'allocated_amount' => $allocated,
                ]);

                $this->settleUp($open[$purchaseId], $allocated);
            }

            // The whole amount handed over, not just the allocated part.
            $this->ledger->recordSupplier(
                supplier: $supplier,
                type: SupplierLedgerEntryType::Payment,
                amount: $amount,
                entryDate: $paymentDate,
                reference: $payment,
                note: $data['note'] ?? null,
                createdBy: $userId,
            );

            // withdraw, not record: a cash box cannot go below nothing, and a
            // refusal takes the payment and every allocation with it.
            $this->cash->withdraw(
                account: $account,
                amount: $amount,
                txnDate: $paymentDate,
                source: TransactionSource::PurchasePayment,
                party: $supplier,
                sourceId: $payment->id,
                paymentMethod: $this->methodFrom($data),
                note: $data['note'] ?? null,
                createdBy: $userId,
                shopId: $data['shop_id'] ?? $account->shop_id,
            );

            return $payment->refresh();
        });
    }

    /**
     * The supplier's unsettled challans, locked and keyed by id, oldest first.
     *
     * @return array<int, Purchase>
     */
    private function lockOpenPurchases(Supplier $supplier): array
    {
        return Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->owing()
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * Spreads a payment over the oldest challans until it runs out.
     *
     * @param  array<int, Purchase>  $open
     * @return array<int, string>
     */
    private function oldestFirst(array $open, string $amount): array
    {
        $left = $amount;
        $spread = [];

        foreach ($open as $id => $purchase) {
            if (bccomp($left, '0.00', 2) <= 0) {
                break;
            }

            $due = (string) $purchase->due_amount;
            $take = bccomp($left, $due, 2) >= 0 ? $due : $left;

            $spread[$id] = $take;
            $left = bcsub($left, $take, 2);
        }

        return $spread;
    }

    /**
     * Checks an allocation the caller chose themselves.
     *
     * @param  array<int, Purchase>  $open
     * @param  array<int, string>  $allocations
     * @return array<int, string>
     */
    private function asGiven(array $open, array $allocations, string $amount): array
    {
        $spread = [];
        $total = '0.00';

        foreach ($allocations as $purchaseId => $allocated) {
            $allocated = $this->money($allocated);

            if (bccomp($allocated, '0.00', 2) <= 0) {
                throw new InvalidArgumentException('An allocation settles a positive amount.');
            }

            $purchase = $open[(int) $purchaseId] ?? null;

            if ($purchase === null) {
                throw new InvalidArgumentException(
                    "Challan {$purchaseId} is not an open challan of this supplier."
                );
            }

            // Overpaying one challan to make the arithmetic fit would leave a
            // negative due and a purchase that reads as more than paid.
            if (bccomp($allocated, (string) $purchase->due_amount, 2) > 0) {
                throw new InvalidArgumentException(
                    "Challan {$purchase->purchase_no} owes {$purchase->due_amount}, less than the {$allocated} allocated to it."
                );
            }

            $spread[(int) $purchaseId] = $allocated;
            $total = bcadd($total, $allocated, 2);
        }

        if (bccomp($total, $amount, 2) > 0) {
            throw new InvalidArgumentException(
                "The allocations come to {$total}, more than the {$amount} handed over."
            );
        }

        return $spread;
    }

    /**
     * Moves a challan's paid and due figures by what this payment settled.
     */
    private function settleUp(Purchase $purchase, string $allocated): void
    {
        $paid = bcadd((string) $purchase->paid_amount, $allocated, 2);
        $due = bcsub((string) $purchase->total_amount, $paid, 2);

        $purchase->forceFill([
            'paid_amount' => $paid,
            'due_amount' => $due,
            'status' => bccomp($due, '0.00', 2) <= 0 ? PurchaseStatus::Paid : PurchaseStatus::Partial,
        ])->save();
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

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
