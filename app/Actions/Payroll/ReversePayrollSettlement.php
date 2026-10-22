<?php

namespace App\Actions\Payroll;

use App\Models\PayrollSettlement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Reverses a finalized settlement. The row is kept for the record and only
 * marked reversed, which frees the period to be finalized again with corrected
 * figures. Nothing is deleted, so the history stays complete.
 */
class ReversePayrollSettlement
{
    public function handle(PayrollSettlement $settlement, ?int $reversedBy = null, ?string $note = null): PayrollSettlement
    {
        return DB::transaction(function () use ($settlement, $reversedBy, $note) {
            // Already reversed: leave it be, so a double submit is harmless.
            if ($settlement->reversed_at !== null) {
                return $settlement;
            }

            $settlement->update([
                'reversed_at' => CarbonImmutable::now(),
                'reversed_by' => $reversedBy,
                'note' => $note ?? $settlement->note,
            ]);

            return $settlement;
        });
    }
}
