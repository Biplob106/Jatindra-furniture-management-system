<?php

namespace App\Actions\Expenses;

use App\Enums\CashPaymentMethod;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Expense;
use App\Services\CashService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Records a shop expense and the money leaving to pay it.
 *
 * Section 9: an expense writes the operational row and a transactions row out.
 * Both or neither, so the daily closing can never show a cost with no matching
 * withdrawal, or the reverse.
 */
class RecordExpense
{
    public function __construct(private readonly CashService $cash) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Account $account, ?int $userId = null): Expense
    {
        $amount = (string) $data['amount'];

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('An expense is a positive amount.');
        }

        $method = match (true) {
            ! isset($data['payment_method']) => PaymentMethod::Cash,
            $data['payment_method'] instanceof PaymentMethod => $data['payment_method'],
            default => PaymentMethod::from($data['payment_method']),
        };

        return DB::transaction(function () use ($data, $account, $amount, $method, $userId) {
            $expense = Expense::create([
                'shop_id' => $data['shop_id'] ?? $account->shop_id,
                'category_id' => $data['category_id'],
                'expense_date' => $data['expense_date'],
                'amount' => $amount,
                'paid_to' => $data['paid_to'] ?? null,
                'payment_method' => $method,
                'account_id' => $account->id,
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            // withdraw, not record: a cash box cannot go below nothing, and a
            // refusal takes the expense row with it.
            $this->cash->withdraw(
                account: $account,
                amount: $amount,
                txnDate: $data['expense_date'],
                source: TransactionSource::Expense,
                sourceId: $expense->id,
                paymentMethod: CashPaymentMethod::from($method->value),
                note: $data['note'] ?? $expense->category->name,
                createdBy: $userId,
                shopId: $expense->shop_id,
            );

            return $expense;
        });
    }
}
