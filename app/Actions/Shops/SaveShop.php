<?php

namespace App\Actions\Shops;

use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a shop. Passing null for $shop creates one.
 *
 * @param  array<string, mixed>  $data
 */
class SaveShop
{
    public function handle(array $data, ?Shop $shop = null): Shop
    {
        return DB::transaction(function () use ($data, $shop) {
            $shop ??= new Shop;

            $shop->fill($data)->save();

            return $shop;
        });
    }
}
