<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The only thing that writes transactions, and the only thing that writes
 * accounts.current_balance.
 *
 * A row here means physical money moved. Nothing else may insert into
 * transactions, because a row written without the matching balance update
 * silently desyncs the cash box from the books.
 *
 * current_balance is the one deliberate exception to the no-running-balance
 * rule. It is maintained inside the same DB transaction as the transactions
 * row, with the account row locked, so two payments recorded at once cannot
 * both read the same starting figure.
 */
class CashService
{
    /**
     * Records one movement of money and moves the account balance with it.
     *
     * @param  string  $amount  Money as a string. Never a float.
     */
    public function record(
        Account $account,
        TransactionDirection $direction,
        string $amount,
        string $txnDate,
        TransactionSource $source,
        ?Model $party = null,
        ?int $sourceId = null,
        CashPaymentMethod $paymentMethod = CashPaymentMethod::Cash,
        ?string $note = null,
        ?int $createdBy = null,
        ?int $shopId = null,
    ): Transaction {
        // Shape first, then value. bccomp() raises its own ValueError on a
        // malformed string, and the rejection should be this method's
        // decision rather than a side effect of the comparison.
        $this->assertPlainDecimal($amount);

        if (bccomp($amount, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('A transaction moves a positive amount. Use the direction to say which way.');
        }

        return DB::transaction(function () use ($account, $direction, $amount, $txnDate, $source, $party, $sourceId, $paymentMethod, $note, $createdBy, $shopId) {
            // Lock the account for the life of this transaction so a
            // concurrent payment cannot read the same starting balance.
            $locked = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            $transaction = Transaction::create([
                'txn_date' => $txnDate,
                'shop_id' => $shopId ?? $locked->shop_id,
                'account_id' => $locked->id,
                'direction' => $direction,
                'amount' => $amount,
                'source_type' => $source,
                'source_id' => $sourceId,
                'party_type' => $party ? $party::class : null,
                'party_id' => $party?->getKey(),
                'payment_method' => $paymentMethod,
                'note' => $note,
                'created_by' => $createdBy,
            ]);

            // Arithmetic in SQL rather than PHP, so the stored value is moved
            // by exactly the amount written and never round-trips a float.
            Account::whereKey($locked->id)->update([
                'current_balance' => DB::raw(
                    'current_balance '.($direction === TransactionDirection::In ? '+' : '-').' '.$amount
                ),
            ]);

            $account->refresh();

            return $transaction;
        });
    }

    /**
     * Records money leaving an account, refusing to overdraw a cash box.
     *
     * A drawer cannot hold less than nothing. Bank and mobile accounts are
     * allowed to go negative, since an overdraft is a real thing there.
     */
    public function withdraw(
        Account $account,
        string $amount,
        string $txnDate,
        TransactionSource $source,
        ?Model $party = null,
        ?int $sourceId = null,
        CashPaymentMethod $paymentMethod = CashPaymentMethod::Cash,
        ?string $note = null,
        ?int $createdBy = null,
        ?int $shopId = null,
    ): Transaction {
        $this->assertPlainDecimal($amount);

        if ($account->type === AccountType::Cash
            && bccomp((string) $account->current_balance, $amount, 2) < 0) {
            throw new RuntimeException(
                'এই হিসাবে যত টাকা আছে তার বেশি দেওয়া যাবে না। বর্তমান জমা ৳ '.$account->current_balance.'।'
            );
        }

        return $this->record(
            account: $account,
            direction: TransactionDirection::Out,
            amount: $amount,
            txnDate: $txnDate,
            source: $source,
            party: $party,
            sourceId: $sourceId,
            paymentMethod: $paymentMethod,
            note: $note,
            createdBy: $createdBy,
            shopId: $shopId,
        );
    }

    /**
     * What the transactions rows say an account holds, computed from scratch.
     *
     * current_balance should always equal this. They are compared in a test,
     * and a reconciliation screen will compare them for real later.
     */
    public function computedBalanceFor(Account $account): string
    {
        $movement = Transaction::query()
            ->where('account_id', $account->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) AS movement")
            ->value('movement');

        return bcadd((string) $account->opening_balance, number_format((float) $movement, 2, '.', ''), 2);
    }

    /**
     * The amount is interpolated into the balance update, so nothing but a
     * plain decimal may reach it.
     */
    private function assertPlainDecimal(string $amount): void
    {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException("Refusing to record a non-decimal amount: {$amount}");
        }
    }
}
