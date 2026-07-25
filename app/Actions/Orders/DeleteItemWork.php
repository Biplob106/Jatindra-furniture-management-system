<?php

namespace App\Actions\Orders;

use App\Enums\LedgerEntryType;
use App\Enums\OrderItemWorkStatus;
use App\Models\EmployeeLedger;
use App\Models\OrderItemWork;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * Removes a work assignment.
 *
 * Work that is already done is not removable. Its credit is money the worker
 * has earned, and ledger rows are never deleted; a job done in error is
 * corrected with an adjustment entry, not by making the record disappear.
 */
class DeleteItemWork
{
    public function handle(OrderItemWork $work): void
    {
        if ($work->status === OrderItemWorkStatus::Done) {
            throw new ReferencedRecordException(
                'শেষ হয়ে যাওয়া কাজ মুছে ফেলা যাবে না। ভুল হলে সংশোধন এন্ট্রি দিন।'
            );
        }

        DB::transaction(function () use ($work) {
            // Nothing should be here, since only `done` pays, but a stray row
            // from a status that was walked back would otherwise be orphaned.
            EmployeeLedger::query()
                ->where('reference_type', OrderItemWork::class)
                ->where('reference_id', $work->id)
                ->where('type', LedgerEntryType::PieceEarned)
                ->delete();

            $work->delete();
        });
    }
}
