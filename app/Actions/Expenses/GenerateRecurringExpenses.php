<?php

namespace App\Actions\Expenses;

use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Posts the monthly shop rent.
 *
 * The third idempotency case CLAUDE.md names: one rent expense per shop per
 * month, however many times this runs. The guard is a lookup for an existing
 * rent row in the same month for the same shop, so a second run on the same
 * day, or a manual run after the scheduled one, adds nothing.
 *
 * Rent is dated the shop's rent_due_day, or the last day of the month when
 * that day does not exist, which is what a 31st due date means in February.
 */
class GenerateRecurringExpenses
{
    /** The category rent is posted against. */
    public const RENT_CATEGORY = 'দোকান ভাড়া';

    public function __construct(private readonly RecordExpense $recordExpense) {}

    /**
     * @param  string  $month  Any date inside the month, or YYYY-MM.
     * @return array{posted: int, skipped: int, total: string}
     */
    public function handle(string $month, ?Account $account = null, ?int $userId = null): array
    {
        $period = $this->periodOf($month);

        $category = ExpenseCategory::where('name', self::RENT_CATEGORY)->first();

        if ($category === null) {
            throw new RuntimeException(
                'ভাড়ার খাত "'.self::RENT_CATEGORY.'" পাওয়া যায়নি। আগে খরচের খাত যোগ করুন।'
            );
        }

        $account ??= Account::where('is_active', true)->orderBy('id')->first();

        if ($account === null) {
            throw new RuntimeException('কোনো সক্রিয় হিসাব নেই, তাই ভাড়া দেওয়া যাবে না।');
        }

        $posted = 0;
        $skipped = 0;
        $total = '0.00';

        $shops = Shop::query()
            ->where('is_active', true)
            ->where('monthly_rent', '>', 0)
            ->get();

        foreach ($shops as $shop) {
            if ($this->alreadyPosted($shop, $category, $period)) {
                $skipped++;

                continue;
            }

            $this->recordExpense->handle([
                'shop_id' => $shop->id,
                'category_id' => $category->id,
                'expense_date' => $this->dueDateFor($shop, $period),
                'amount' => (string) $shop->monthly_rent,
                'paid_to' => $shop->landlord_name,
                'payment_method' => PaymentMethod::Cash,
                'note' => $period->format('F Y').' মাসের দোকান ভাড়া',
            ], $account, $userId);

            $posted++;
            $total = bcadd($total, (string) $shop->monthly_rent, 2);
        }

        return ['posted' => $posted, 'skipped' => $skipped, 'total' => $total];
    }

    private function alreadyPosted(Shop $shop, ExpenseCategory $category, CarbonImmutable $period): bool
    {
        return Expense::query()
            ->where('shop_id', $shop->id)
            ->where('category_id', $category->id)
            ->whereBetween('expense_date', [
                $period->startOfMonth()->toDateString(),
                $period->endOfMonth()->toDateString(),
            ])
            ->exists();
    }

    /**
     * The rent due day, clamped into the month. A shop due on the 31st pays on
     * the 28th in February rather than spilling into March.
     */
    private function dueDateFor(Shop $shop, CarbonImmutable $period): string
    {
        $lastDay = (int) $period->endOfMonth()->format('j');
        $day = min($shop->rent_due_day ?: 1, $lastDay);

        return $period->startOfMonth()->addDays($day - 1)->toDateString();
    }

    private function periodOf(string $month): CarbonImmutable
    {
        return preg_match('/^\d{4}-\d{2}$/', $month)
            ? CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')
            : CarbonImmutable::parse($month);
    }
}
