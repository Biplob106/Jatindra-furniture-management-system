<?php

namespace App\Actions\Trades;

use App\Models\Trade;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $data
 */
class SaveTrade
{
    public function handle(array $data, ?Trade $trade = null): Trade
    {
        return DB::transaction(function () use ($data, $trade) {
            $trade ??= new Trade;

            $trade->fill($data)->save();

            return $trade;
        });
    }
}
