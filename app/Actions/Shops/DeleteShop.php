<?php

namespace App\Actions\Shops;

use App\Models\Employee;
use App\Models\Shop;
use App\Models\User;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a shop, unless operational rows point at it.
 *
 * The reference list grows as later phases land: orders, transactions,
 * expenses and daily_closings all carry a shop_id.
 */
class DeleteShop
{
    public function handle(Shop $shop): void
    {
        $blockers = [
            'ব্যবহারকারী' => User::where('shop_id', $shop->id)->count(),
            'কর্মী' => Employee::where('shop_id', $shop->id)->count(),
        ];

        ReferencedRecordException::throwIfReferenced('দোকান', $blockers);

        DB::transaction(fn () => $shop->delete());
    }
}
