<?php

namespace App\Actions\Cash;

use App\Models\DailyClosing;
use App\Models\Shop;
use App\Queries\DailyCashFigures;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Closes a shop's day: what the books say the drawer holds, against what was
 * counted.
 *
 * Every figure but the counted cash is recomputed here rather than taken from
 * the form. The person closing counts notes; they do not get to decide what
 * the books say, because the difference between the two is the entire point.
 *
 * Closing the same day twice updates the one row. That is what the unique key
 * on (shop_id, closing_date) is for: a day recounted after a mistake should
 * correct itself, not become two conflicting answers.
 */
class CloseDay
{
    public function __construct(private readonly DailyCashFigures $figures) {}

    public function handle(
        Shop $shop,
        string $date,
        string $countedCash,
        ?string $note = null,
        ?int $userId = null,
    ): DailyClosing {
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $countedCash)) {
            throw new InvalidArgumentException("Counted cash must be a plain amount: {$countedCash}");
        }

        return DB::transaction(function () use ($shop, $date, $countedCash, $note, $userId) {
            $figures = $this->figures->forShopOn($shop->id, $date);

            // Positive means the drawer holds more than the books expect.
            $difference = bcsub($countedCash, $figures['expected_closing'], 2);

            return DailyClosing::updateOrCreate(
                ['shop_id' => $shop->id, 'closing_date' => $date],
                [
                    ...$figures,
                    'counted_cash' => $countedCash,
                    'difference' => $difference,
                    'closed_by' => $userId,
                    'closed_at' => now(),
                    'note' => $note,
                ]
            );
        });
    }
}
