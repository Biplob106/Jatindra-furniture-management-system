<?php

namespace App\Actions\Trades;

use App\Models\Employee;
use App\Models\Trade;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

class DeleteTrade
{
    public function handle(Trade $trade): void
    {
        ReferencedRecordException::throwIfReferenced('কাজের ধরন', [
            'কর্মী' => Employee::where('trade_id', $trade->id)->count(),
        ]);

        DB::transaction(fn () => $trade->delete());
    }
}
