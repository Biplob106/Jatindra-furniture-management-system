<?php

namespace Database\Seeders;

use App\Actions\Expenses\GenerateRecurringExpenses;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * The short list of running-cost headings.
 *
 * Expenses are for money that is gone once it is paid. Anything that becomes
 * stock (wood, board, hardware, paint) is a purchase, so there is deliberately
 * no heading for it here: an expense for wood skips stock, the supplier ledger
 * and the order's material cost all at once.
 *
 * Rerunnable against a live shop. A category is matched by name including
 * soft-deleted ones, so the owner's edits stick and a heading they deleted
 * does not come back.
 */
class ExpenseCategorySeeder extends Seeder
{
    /**
     * @var list<array{name: string, is_recurring: bool}>
     */
    public const DEFAULTS = [
        ['name' => GenerateRecurringExpenses::RENT_CATEGORY, 'is_recurring' => true],
        ['name' => 'কারেন্ট বিল', 'is_recurring' => false],
        ['name' => 'আপ্যায়ন', 'is_recurring' => false],
        ['name' => 'ট্রান্সপোর্ট', 'is_recurring' => false],
        ['name' => 'মেশিন মেরামত', 'is_recurring' => false],
        ['name' => 'লাইসেন্স ও ফি', 'is_recurring' => false],
        ['name' => 'কারখানার ছোটখাটো খরচ', 'is_recurring' => false],
        ['name' => 'অন্যান্য', 'is_recurring' => false],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $category) {
            ExpenseCategory::withTrashed()->firstOrCreate(
                ['name' => $category['name']],
                ['is_recurring' => $category['is_recurring'], 'is_active' => true],
            );
        }
    }
}
