<?php

use App\Enums\Role;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->accountant = User::factory()->create();
    $this->accountant->assignRole(Role::Accountant->value);

    $this->account = Account::factory()->create(['opening_balance' => 100000, 'current_balance' => 100000]);
    $this->category = ExpenseCategory::factory()->create(['name' => 'কারেন্ট বিল']);

    Carbon::setTestNow('2026-07-25 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function expensePayload(array $overrides = []): array
{
    return [
        'category_id' => test()->category->id,
        'account_id' => test()->account->id,
        'expense_date' => '2026-07-20',
        'amount' => '3500',
        ...$overrides,
    ];
}

it('records an expense and the money leaving', function () {
    $this->actingAs($this->accountant)
        ->post('/expenses', expensePayload())
        ->assertRedirect('/expenses');

    expect(Expense::count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and($this->account->fresh()->current_balance)->toBe('96500.00');
});

it('lists the month with its total', function () {
    Expense::factory()->count(3)->create([
        'category_id' => $this->category->id,
        'expense_date' => '2026-07-10',
        'amount' => 1000,
    ]);

    $this->actingAs($this->accountant)
        ->get('/expenses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('expenses.data', 3)->where('monthTotal', '3000.00'));
});

it('leaves out other months', function () {
    Expense::factory()->create(['category_id' => $this->category->id, 'expense_date' => '2026-07-10', 'amount' => 1000]);
    Expense::factory()->create(['category_id' => $this->category->id, 'expense_date' => '2026-06-10', 'amount' => 5000]);

    $this->actingAs($this->accountant)
        ->get('/expenses?month=2026-07')
        ->assertInertia(fn ($page) => $page->has('expenses.data', 1)->where('monthTotal', '1000.00'));
});

/**
 * Where the money went, biggest first. This is what the owner actually asks.
 */
it('breaks the month down by category, biggest first', function () {
    $rent = ExpenseCategory::factory()->create(['name' => 'দোকান ভাড়া']);

    Expense::factory()->create(['category_id' => $this->category->id, 'expense_date' => '2026-07-10', 'amount' => 2000]);
    Expense::factory()->create(['category_id' => $rent->id, 'expense_date' => '2026-07-05', 'amount' => 15000]);

    $this->actingAs($this->accountant)
        ->get('/expenses')
        ->assertInertia(fn ($page) => $page
            ->where('byCategory.0.name', 'দোকান ভাড়া')
            ->where('byCategory.0.total', '15000.00')
            ->where('byCategory.1.name', 'কারেন্ট বিল')
        );
});

it('filters to one category', function () {
    $rent = ExpenseCategory::factory()->create(['name' => 'দোকান ভাড়া']);

    Expense::factory()->create(['category_id' => $this->category->id, 'expense_date' => '2026-07-10', 'amount' => 2000]);
    Expense::factory()->create(['category_id' => $rent->id, 'expense_date' => '2026-07-05', 'amount' => 15000]);

    $this->actingAs($this->accountant)
        ->get("/expenses?category_id={$rent->id}")
        ->assertInertia(fn ($page) => $page->has('expenses.data', 1)->where('monthTotal', '15000.00'));
});

it('falls back to this month when the month is nonsense', function () {
    $this->actingAs($this->accountant)
        ->get('/expenses?month=not-a-month')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('month', '2026-07'));
});

it('refuses a future date', function () {
    $this->actingAs($this->accountant)
        ->post('/expenses', expensePayload(['expense_date' => '2026-07-26']))
        ->assertSessionHasErrors('expense_date');

    expect(Expense::count())->toBe(0);
});

it('refuses a zero amount', function () {
    $this->actingAs($this->accountant)
        ->post('/expenses', expensePayload(['amount' => '0']))
        ->assertSessionHasErrors('amount');
});

it('refuses an expense with no account', function () {
    $this->actingAs($this->accountant)
        ->post('/expenses', expensePayload(['account_id' => null]))
        ->assertSessionHasErrors('account_id');

    expect(Expense::count())->toBe(0);
});

/**
 * The drawer refusing is a runtime failure, not a validation one. It has to
 * reach the user readably with nothing written.
 */
it('reports a Bengali message when the drawer cannot cover it', function () {
    $small = Account::factory()->create(['opening_balance' => 100, 'current_balance' => 100]);

    $this->actingAs($this->accountant)
        ->post('/expenses', expensePayload(['account_id' => $small->id]))
        ->assertSessionHas('error');

    expect(Expense::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($small->fresh()->current_balance)->toBe('100.00');
});

it('stamps who recorded it', function () {
    $this->actingAs($this->accountant)->post('/expenses', expensePayload());

    expect(Expense::sole()->created_by)->toBe($this->accountant->id);
});

/**
 * There is deliberately no edit or delete: an expense has a cash row behind it.
 */
it('offers no way to edit or delete an expense', function () {
    $expense = Expense::factory()->create(['category_id' => $this->category->id]);

    $this->actingAs($this->accountant)->get("/expenses/{$expense->id}/edit")->assertNotFound();
    $this->actingAs($this->accountant)->put("/expenses/{$expense->id}")->assertNotFound();
    $this->actingAs($this->accountant)->delete("/expenses/{$expense->id}")->assertNotFound();

    expect(Expense::count())->toBe(1);
});

it('lets a manager record expenses too', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)->post('/expenses', expensePayload())->assertRedirect();

    expect(Expense::count())->toBe(1);
});

it('keeps a storekeeper out', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/expenses')->assertForbidden();
    $this->actingAs($storekeeper)->post('/expenses', expensePayload())->assertForbidden();

    expect(Expense::count())->toBe(0);
});
