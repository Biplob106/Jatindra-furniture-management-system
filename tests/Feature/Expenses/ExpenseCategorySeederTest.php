<?php

use App\Actions\Expenses\GenerateRecurringExpenses;
use App\Models\ExpenseCategory;
use Database\Seeders\ExpenseCategorySeeder;

it('seeds the short list of running-cost categories', function () {
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::orderBy('id')->pluck('name')->all())->toBe([
        'দোকান ভাড়া',
        'কারেন্ট বিল',
        'আপ্যায়ন',
        'ট্রান্সপোর্ট',
        'মেশিন মেরামত',
        'লাইসেন্স ও ফি',
        'কারখানার ছোটখাটো খরচ',
        'অন্যান্য',
    ]);
});

it('marks only rent as recurring, under the name the rent command looks for', function () {
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::where('is_recurring', true)->pluck('name')->all())
        ->toBe([GenerateRecurringExpenses::RENT_CATEGORY]);
});

it('adds nothing on a second run', function () {
    $this->seed(ExpenseCategorySeeder::class);
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::withTrashed()->count())->toBe(8);
});

it('keeps categories the shop already has and fills in only the missing ones', function () {
    ExpenseCategory::factory()->create(['name' => 'কারেন্ট বিল', 'is_recurring' => true]);
    ExpenseCategory::factory()->create(['name' => 'গ্যাস বিল']);

    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::count())->toBe(9)
        ->and(ExpenseCategory::where('name', 'কারেন্ট বিল')->sole()->is_recurring)->toBeTrue();
});

it('does not bring back a category the owner deleted', function () {
    ExpenseCategory::factory()->create(['name' => 'আপ্যায়ন'])->delete();

    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::where('name', 'আপ্যায়ন')->exists())->toBeFalse()
        ->and(ExpenseCategory::withTrashed()->count())->toBe(8);
});

it('has no heading for goods that belong on a purchase', function () {
    $this->seed(ExpenseCategorySeeder::class);

    expect(ExpenseCategory::pluck('name')->all())
        ->each->not->toMatch('/কাঠ|বোর্ড|হার্ডওয়্যার|রং|কেনা/u');
});
