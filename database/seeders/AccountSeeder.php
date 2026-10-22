<?php

namespace Database\Seeders;

use App\Actions\Accounts\SaveAccount;
use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * Gives a fresh shop one cash account to pay from and record takings into.
 *
 * A shop earns wages as a due the moment work is done, with no money needed;
 * money is only needed to hand it over, and that has to come from somewhere.
 * Without an account there is nowhere to record a payment at all, so a brand
 * new install starts with a single "ক্যাশ" box at zero.
 *
 * Rerunnable and safe on a live shop: it does nothing once any account exists,
 * so it never touches real balances or adds a second cash box.
 */
class AccountSeeder extends Seeder
{
    public function run(SaveAccount $save): void
    {
        if (Account::query()->exists()) {
            return;
        }

        $save->handle([
            'name' => 'ক্যাশ',
            'type' => AccountType::Cash->value,
            'opening_balance' => 0,
            'is_active' => true,
        ]);
    }
}
