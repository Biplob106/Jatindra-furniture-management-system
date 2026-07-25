<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use Illuminate\Support\Facades\DB;

/**
 * current_balance is never in $data. It is the one running-balance column in
 * the schema and only CashService may write it. On create it starts equal to
 * the opening balance; after that nothing here touches it.
 *
 * @param  array<string, mixed>  $data
 */
class SaveAccount
{
    public function handle(array $data, ?Account $account = null): Account
    {
        unset($data['current_balance']);

        return DB::transaction(function () use ($data, $account) {
            if ($account === null) {
                $account = new Account;
                $account->fill($data);
                $account->current_balance = $data['opening_balance'] ?? 0;
                $account->save();

                return $account;
            }

            $account->fill($data)->save();

            return $account;
        });
    }
}
