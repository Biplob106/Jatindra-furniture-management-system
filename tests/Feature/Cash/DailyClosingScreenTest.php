<?php

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\DailyClosing;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->accountant = User::factory()->create();
    $this->accountant->assignRole(Role::Accountant->value);

    $this->shop = Shop::factory()->create();
    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 5000,
        'current_balance' => 5000,
    ]);

    Carbon::setTestNow('2026-07-20 20:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows what the books expect in the drawer', function () {
    app(CashService::class)->record(
        $this->drawer, TransactionDirection::In, '12000', '2026-07-20',
        TransactionSource::Sale, shopId: $this->shop->id
    );

    $this->actingAs($this->accountant)
        ->get('/daily-closing')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('figures.opening_balance', '5000.00')
            ->where('figures.total_in', '12000.00')
            ->where('figures.expected_closing', '17000.00')
            ->where('existing', null)
        );
});

it('picks the shop without asking when there is only one', function () {
    $this->actingAs($this->accountant)
        ->get('/daily-closing')
        ->assertInertia(fn ($page) => $page->where('shopId', $this->shop->id));
});

it('uses the shop the user is posted to', function () {
    $otherShop = Shop::factory()->create(['name' => 'অন্য দোকান']);
    $this->accountant->update(['shop_id' => $otherShop->id]);

    $this->actingAs($this->accountant)
        ->get('/daily-closing')
        ->assertInertia(fn ($page) => $page->where('shopId', $otherShop->id));
});

it('pulls a future date back to today', function () {
    $this->actingAs($this->accountant)
        ->get('/daily-closing?date=2027-01-01')
        ->assertInertia(fn ($page) => $page->where('date', '2026-07-20'));
});

it('closes the day and records the difference', function () {
    app(CashService::class)->record(
        $this->drawer, TransactionDirection::In, '10000', '2026-07-20',
        TransactionSource::Sale, shopId: $this->shop->id
    );

    $this->actingAs($this->accountant)
        ->post('/daily-closing', [
            'shop_id' => $this->shop->id,
            'closing_date' => '2026-07-20',
            'counted_cash' => '14500',
            'note' => 'একটু কম',
        ])
        ->assertRedirect();

    $closing = DailyClosing::sole();

    expect($closing->expected_closing)->toBe('15000.00')
        ->and($closing->counted_cash)->toBe('14500.00')
        ->and($closing->difference)->toBe('-500.00')
        ->and($closing->closed_by)->toBe($this->accountant->id)
        ->and($closing->note)->toBe('একটু কম');
});

it('shows a day that was already closed', function () {
    $this->actingAs($this->accountant)->post('/daily-closing', [
        'shop_id' => $this->shop->id,
        'closing_date' => '2026-07-20',
        'counted_cash' => '5000',
    ]);

    $this->actingAs($this->accountant)
        ->get('/daily-closing?date=2026-07-20')
        ->assertInertia(fn ($page) => $page
            ->where('existing.counted_cash', '5000.00')
            ->where('existing.difference', '0.00')
            ->where('existing.closed_by', $this->accountant->name)
        );
});

/**
 * The counted figure is the only thing the form decides. Everything else is
 * recomputed, because the difference between the two is the entire point.
 */
it('ignores book figures sent by the client', function () {
    app(CashService::class)->record(
        $this->drawer, TransactionDirection::In, '10000', '2026-07-20',
        TransactionSource::Sale, shopId: $this->shop->id
    );

    $this->actingAs($this->accountant)->post('/daily-closing', [
        'shop_id' => $this->shop->id,
        'closing_date' => '2026-07-20',
        'counted_cash' => '15000',
        'expected_closing' => '1',
        'opening_balance' => '1',
        'total_in' => '1',
        'difference' => '99999',
    ]);

    $closing = DailyClosing::sole();

    expect($closing->expected_closing)->toBe('15000.00')
        ->and($closing->opening_balance)->toBe('5000.00')
        ->and($closing->total_in)->toBe('10000.00')
        ->and($closing->difference)->toBe('0.00');
});

it('refuses to close a day that has not happened', function () {
    $this->actingAs($this->accountant)
        ->post('/daily-closing', [
            'shop_id' => $this->shop->id,
            'closing_date' => '2026-07-21',
            'counted_cash' => '5000',
        ])
        ->assertSessionHasErrors('closing_date');

    expect(DailyClosing::count())->toBe(0);
});

it('refuses a negative count', function () {
    $this->actingAs($this->accountant)
        ->post('/daily-closing', [
            'shop_id' => $this->shop->id,
            'closing_date' => '2026-07-20',
            'counted_cash' => '-100',
        ])
        ->assertSessionHasErrors('counted_cash');
});

it('accepts an empty drawer', function () {
    app(CashService::class)->record(
        $this->drawer, TransactionDirection::Out, '5000', '2026-07-20',
        TransactionSource::Expense, shopId: $this->shop->id
    );

    $this->actingAs($this->accountant)->post('/daily-closing', [
        'shop_id' => $this->shop->id,
        'closing_date' => '2026-07-20',
        'counted_cash' => '0',
    ]);

    expect(DailyClosing::sole()->difference)->toBe('0.00');
});

it('closes the same day twice into one row', function () {
    foreach (['14000', '15000'] as $counted) {
        $this->actingAs($this->accountant)->post('/daily-closing', [
            'shop_id' => $this->shop->id,
            'closing_date' => '2026-07-20',
            'counted_cash' => $counted,
        ]);
    }

    expect(DailyClosing::count())->toBe(1)
        ->and(DailyClosing::sole()->counted_cash)->toBe('15000.00');
});

it('lists the last few days so a run of short drawers shows up', function () {
    foreach (['2026-07-18', '2026-07-19', '2026-07-20'] as $day) {
        DailyClosing::factory()->create([
            'shop_id' => $this->shop->id,
            'closing_date' => $day,
            'counted_cash' => 5000,
            'expected_closing' => 5200,
            'difference' => -200,
        ]);
    }

    $this->actingAs($this->accountant)
        ->get('/daily-closing')
        ->assertInertia(fn ($page) => $page
            ->has('recent', 3)
            ->where('recent.0.closing_date', '2026-07-20')
            ->where('recent.0.difference', '-200.00')
        );
});

/**
 * The seeder gives daily_closing to the accountant and owner only. A manager
 * runs the floor; counting the drawer is the bookkeeper's job.
 */
it('keeps a manager out, since closing is the bookkeeper\'s job', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)->get('/daily-closing')->assertForbidden();

    $this->actingAs($manager)
        ->post('/daily-closing', [
            'shop_id' => $this->shop->id,
            'closing_date' => '2026-07-20',
            'counted_cash' => '5000',
        ])
        ->assertForbidden();

    expect(DailyClosing::count())->toBe(0);
});

it('keeps a storekeeper out', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/daily-closing')->assertForbidden();

    $this->actingAs($storekeeper)
        ->post('/daily-closing', [
            'shop_id' => $this->shop->id,
            'closing_date' => '2026-07-20',
            'counted_cash' => '5000',
        ])
        ->assertForbidden();

    expect(DailyClosing::count())->toBe(0);
});
