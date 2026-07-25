<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Issues the printable document numbers: SH-2607-0142 and its siblings.
 *
 * These get written on the paper slip during the transition period, so they
 * have to be short, human-readable, and gapless enough that a missing number
 * reads as a lost order rather than an abandoned draft. Orders are numbered on
 * confirmation, not on create, for exactly that reason.
 *
 * Safe under concurrency. The counter row is created with insertOrIgnore, so
 * two callers arriving in the same month cannot collide on the insert, then
 * locked for update, so the increment is serialised. Two clerks confirming at
 * the same moment get 0142 and 0143, never 0142 twice.
 */
class NumberSeries
{
    /** Document prefixes, one counter each. */
    public const ORDER = 'SH';

    public const SALE = 'INV';

    public const PURCHASE = 'PO';

    public const CNC_JOB = 'CNC';

    /**
     * Takes the next number for a prefix and returns it formatted.
     *
     * @param  string  $prefix  One of the constants above.
     * @param  string|null  $onDate  Any date in the period. Defaults to today.
     */
    public function issue(string $prefix, ?string $onDate = null): string
    {
        $this->assertKnownPrefix($prefix);

        $period = ($onDate ? CarbonImmutable::parse($onDate) : CarbonImmutable::today())->format('ym');

        return DB::transaction(function () use ($prefix, $period) {
            // insertOrIgnore rather than firstOrCreate: two callers racing to
            // start a new month would otherwise collide on the unique key.
            DB::table('number_series')->insertOrIgnore([
                'prefix' => $prefix,
                'period' => $period,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('number_series')
                ->where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $next = $row->last_number + 1;

            DB::table('number_series')
                ->where('id', $row->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $this->format($prefix, $period, $next);
        });
    }

    /**
     * What the next number would be, without taking it. For display only:
     * anything acting on this is racing whoever calls issue() next.
     */
    public function peek(string $prefix, ?string $onDate = null): string
    {
        $this->assertKnownPrefix($prefix);

        $period = ($onDate ? CarbonImmutable::parse($onDate) : CarbonImmutable::today())->format('ym');

        $last = DB::table('number_series')
            ->where('prefix', $prefix)
            ->where('period', $period)
            ->value('last_number') ?? 0;

        return $this->format($prefix, $period, $last + 1);
    }

    private function format(string $prefix, string $period, int $number): string
    {
        return sprintf('%s-%s-%04d', $prefix, $period, $number);
    }

    private function assertKnownPrefix(string $prefix): void
    {
        $known = [self::ORDER, self::SALE, self::PURCHASE, self::CNC_JOB];

        if (! in_array($prefix, $known, true)) {
            throw new InvalidArgumentException("Unknown number series prefix: {$prefix}");
        }
    }
}
