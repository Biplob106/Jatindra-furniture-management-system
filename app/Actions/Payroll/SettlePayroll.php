<?php

namespace App\Actions\Payroll;

use App\Models\PayrollSettlement;
use App\Queries\PayrollSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Finalizes a payroll period by freezing each worker's figures into an
 * immutable snapshot.
 *
 * Figures come from PayrollSummary, the one authoritative calculation, so a
 * settlement can never disagree with what the report showed. A worker already
 * finalized for the exact period is skipped, not re-written: a period is
 * finalized once, and correcting it is a reversal followed by a fresh finalize.
 */
class SettlePayroll
{
    public function __construct(private readonly PayrollSummary $summary) {}

    /**
     * @return array{settled: int, skipped: int}
     */
    public function handle(string $from, string $to, ?int $shopId = null, ?int $settledBy = null): array
    {
        return DB::transaction(function () use ($from, $to, $shopId, $settledBy) {
            $settled = 0;
            $skipped = 0;

            foreach ($this->summary->rows($from, $to, $shopId) as $row) {
                $alreadyFinalized = PayrollSettlement::query()
                    ->where('employee_id', $row['id'])
                    ->where('period_start', $from)
                    ->where('period_end', $to)
                    ->whereNull('reversed_at')
                    ->exists();

                if ($alreadyFinalized) {
                    $skipped++;

                    continue;
                }

                PayrollSettlement::create([
                    'employee_id' => $row['id'],
                    'shop_id' => $shopId,
                    'period_start' => $from,
                    'period_end' => $to,
                    'earned' => $row['earned'],
                    'night' => $row['night'],
                    'paid' => $row['paid'],
                    'deductions' => $row['deductions'],
                    'net_balance' => $row['balance'],
                    'settled_at' => CarbonImmutable::now(),
                    'settled_by' => $settledBy,
                ]);

                $settled++;
            }

            return ['settled' => $settled, 'skipped' => $skipped];
        });
    }
}
