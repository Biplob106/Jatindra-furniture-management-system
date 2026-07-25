<?php

use App\Actions\Expenses\GenerateRecurringExpenses;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\Transaction;
use App\Services\CashService;

beforeEach(function () {
    $this->action = app(GenerateRecurringExpenses::class);

    $this->account = Account::factory()->create(['opening_balance' => 500000, 'current_balance' => 500000]);
    $this->category = ExpenseCategory::factory()->create([
        'name' => GenerateRecurringExpenses::RENT_CATEGORY,
        'is_recurring' => true,
    ]);

    $this->shop = Shop::factory()->create([
        'name' => 'জতীন্দ্র ফার্নিচার',
        'monthly_rent' => 15000,
        'rent_due_day' => 5,
        'landlord_name' => 'আব্দুল করিম',
    ]);
});

it('posts one rent expense with the money leaving', function () {
    $result = $this->action->handle('2026-07');

    expect(Expense::count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and($result['posted'])->toBe(1)
        ->and($result['total'])->toBe('15000.00');

    $expense = Expense::sole();

    expect($expense->amount)->toBe('15000.00')
        ->and($expense->expense_date->toDateString())->toBe('2026-07-05')
        ->and($expense->paid_to)->toBe('আব্দুল করিম')
        ->and($this->account->fresh()->current_balance)->toBe('485000.00');
});

/**
 * The third idempotency case CLAUDE.md names.
 */
it('is idempotent, so a second run posts nothing', function () {
    $this->action->handle('2026-07');

    $expenses = Expense::count();
    $transactions = Transaction::count();
    $balance = $this->account->fresh()->current_balance;

    $second = $this->action->handle('2026-07');

    expect(Expense::count())->toBe($expenses)
        ->and(Transaction::count())->toBe($transactions)
        ->and($this->account->fresh()->current_balance)->toBe($balance)
        ->and($second['posted'])->toBe(0)
        ->and($second['skipped'])->toBe(1);
});

it('posts again the following month', function () {
    $this->action->handle('2026-07');
    $this->action->handle('2026-08');

    expect(Expense::count())->toBe(2)
        ->and($this->account->fresh()->current_balance)->toBe('470000.00');
});

it('skips a rent already entered by hand that month', function () {
    Expense::factory()->create([
        'shop_id' => $this->shop->id,
        'category_id' => $this->category->id,
        'expense_date' => '2026-07-11',
        'amount' => 15000,
    ]);

    $result = $this->action->handle('2026-07');

    expect(Expense::count())->toBe(1)
        ->and($result['posted'])->toBe(0)
        ->and($result['skipped'])->toBe(1);
});

/**
 * A shop due on the 31st still pays in February.
 */
it('clamps a due day that the month does not have', function () {
    $this->shop->update(['rent_due_day' => 31]);

    $this->action->handle('2026-02');

    expect(Expense::sole()->expense_date->toDateString())->toBe('2026-02-28');
});

it('falls back to the first when no due day is set', function () {
    $this->shop->update(['rent_due_day' => null]);

    $this->action->handle('2026-07');

    expect(Expense::sole()->expense_date->toDateString())->toBe('2026-07-01');
});

it('posts for every shop paying rent', function () {
    Shop::factory()->create(['monthly_rent' => 8000, 'rent_due_day' => 10]);

    $result = $this->action->handle('2026-07');

    expect(Expense::count())->toBe(2)
        ->and($result['total'])->toBe('23000.00');
});

it('skips a shop that pays no rent', function () {
    Shop::factory()->create(['monthly_rent' => 0]);

    $result = $this->action->handle('2026-07');

    expect(Expense::count())->toBe(1)
        ->and($result['posted'])->toBe(1);
});

it('skips a shop that has closed', function () {
    Shop::factory()->create(['monthly_rent' => 9000, 'is_active' => false]);

    $this->action->handle('2026-07');

    expect(Expense::count())->toBe(1);
});

it('keeps two shops apart when only one has been posted', function () {
    $second = Shop::factory()->create(['monthly_rent' => 8000, 'rent_due_day' => 10]);

    Expense::factory()->create([
        'shop_id' => $this->shop->id,
        'category_id' => $this->category->id,
        'expense_date' => '2026-07-05',
        'amount' => 15000,
    ]);

    $result = $this->action->handle('2026-07');

    expect($result['posted'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and(Expense::where('shop_id', $second->id)->count())->toBe(1);
});

it('refuses to run when the rent category is missing', function () {
    $this->category->forceDelete();

    expect(fn () => $this->action->handle('2026-07'))->toThrow(RuntimeException::class);

    expect(Expense::count())->toBe(0);
});

it('refuses to run with no active account', function () {
    Account::query()->update(['is_active' => false]);

    expect(fn () => $this->action->handle('2026-07'))->toThrow(RuntimeException::class);

    expect(Expense::count())->toBe(0);
});

it('keeps the account balance in step with its rows', function () {
    Shop::factory()->create(['monthly_rent' => 7250.50, 'rent_due_day' => 3]);

    $this->action->handle('2026-07');

    $account = $this->account->fresh();

    expect($account->current_balance)->toBe('477749.50')
        ->and(app(CashService::class)->computedBalanceFor($account))->toBe('477749.50');
});

it('runs from the command line', function () {
    $this->artisan('rent:generate', ['month' => '2026-07'])->assertSuccessful();

    expect(Expense::count())->toBe(1);
});

it('is safe to run twice from the command line', function () {
    $this->artisan('rent:generate', ['month' => '2026-07'])->assertSuccessful();
    $this->artisan('rent:generate', ['month' => '2026-07'])->assertSuccessful();

    expect(Expense::count())->toBe(1);
});

it('fails loudly from the command line when the category is missing', function () {
    $this->category->forceDelete();

    $this->artisan('rent:generate', ['month' => '2026-07'])->assertFailed();
});
