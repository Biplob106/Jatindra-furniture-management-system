<?php

namespace App\Actions\Orders;

use App\Enums\CashPaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\CashService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Takes money against an order.
 *
 * Section 9 of docs/schema.md, first row: money in, the order's due comes
 * down, and no party ledger is involved. A customer's debt lives on the order
 * itself as due_amount rather than in a ledger table.
 *
 * paid_amount is recalculated from the cash rows that reference this order, not
 * incremented. An increment can drift; a sum cannot disagree with the money it
 * came from.
 *
 * Note on scope: later installments are routed through party_payments and
 * payment_allocations in section 9, which are phase 5 tables. This handles
 * money taken against a single order, which covers the advance at order time
 * and any straight payment afterwards. Allocating one payment across several
 * orders needs those tables.
 */
class RecordOrderAdvance
{
    public function __construct(private readonly CashService $cash) {}

    public function handle(
        Order $order,
        string $amount,
        Account $account,
        string $paidOn,
        CashPaymentMethod $paymentMethod = CashPaymentMethod::Cash,
        ?string $note = null,
        ?int $userId = null,
    ): Transaction {
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('A payment moves a positive amount.');
        }

        return DB::transaction(function () use ($order, $amount, $account, $paidOn, $paymentMethod, $note, $userId) {
            // Lock the order so two clerks taking money at the same counter
            // cannot both read the same due and both accept it.
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            // Derived, not read from due_amount. The stored figure is
            // denormalised and could have drifted; the cash rows cannot.
            // Guarding on the column would let a corrupted value either block
            // a legitimate payment or wave through an overpayment.
            $due = bcsub((string) $locked->total_amount, $this->paidFor($locked), 2);

            if (bccomp($amount, $due, 2) > 0) {
                throw new RuntimeException("বাকি ৳ {$due} এর বেশি টাকা নেওয়া যাবে না।");
            }

            $transaction = $this->cash->record(
                account: $account,
                direction: TransactionDirection::In,
                amount: $amount,
                txnDate: $paidOn,
                source: TransactionSource::OrderPayment,
                party: $locked->customer,
                sourceId: $locked->id,
                paymentMethod: $paymentMethod,
                note: $note ?? 'অর্ডার '.($locked->order_no ?? '#'.$locked->id),
                createdBy: $userId,
                shopId: $locked->shop_id,
            );

            $this->recalculatePaid($locked);

            $order->refresh();

            return $transaction;
        });
    }

    /**
     * Sums what has actually been taken against this order and derives the due
     * from it. Both figures are denormalised columns, so they are rebuilt from
     * the cash rows rather than nudged.
     */
    private function recalculatePaid(Order $order): void
    {
        $paid = $this->paidFor($order);

        $order->forceFill([
            'paid_amount' => $paid,
            'due_amount' => bcsub((string) $order->total_amount, $paid, 2),
        ])->save();
    }

    /**
     * What the cash rows say has been taken against this order. Money out
     * counts against it, so a refund recorded later reduces the paid figure.
     */
    private function paidFor(Order $order): string
    {
        return number_format(
            (float) Transaction::query()
                ->where('source_type', TransactionSource::OrderPayment)
                ->where('source_id', $order->id)
                ->sum(DB::raw("CASE WHEN direction = 'in' THEN amount ELSE -amount END")),
            2, '.', ''
        );
    }
}
