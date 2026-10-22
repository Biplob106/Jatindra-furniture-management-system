<?php

namespace App\Actions\Accounts;

use App\Enums\TransactionSource;
use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\CashService;

/**
 * Records the shop's own cash going into an account — an opening float or money
 * the owner puts in the box. It is tagged capital_in so the cashbook reads it
 * as added capital rather than a sale or an order payment, and it raises the
 * account balance so wages, advances and tiffin can actually be paid from it.
 *
 * Nothing but CashService writes the balance, so this goes through it rather
 * than touching current_balance itself.
 */
class DepositToAccount
{
    public function __construct(private readonly CashService $cash) {}

    public function handle(Account $account, string $amount, string $txnDate, ?string $note = null, ?int $createdBy = null): Transaction
    {
        return $this->cash->record(
            account: $account,
            direction: TransactionDirection::In,
            amount: $amount,
            txnDate: $txnDate,
            source: TransactionSource::CapitalIn,
            note: $note,
            createdBy: $createdBy,
            shopId: $account->shop_id,
        );
    }
}
