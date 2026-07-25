<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * An account holding money is not deletable even before transactions exist:
 * a non-zero balance means the shop has cash sitting in it.
 */
class DeleteAccount
{
    public function handle(Account $account): void
    {
        if (bccomp((string) $account->current_balance, '0.00', 2) !== 0) {
            throw new ReferencedRecordException(
                'এই হিসাবে টাকা জমা আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        DB::transaction(fn () => $account->delete());
    }
}
