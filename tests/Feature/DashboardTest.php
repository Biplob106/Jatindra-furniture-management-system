<?php

use App\Enums\AccountType;
use App\Enums\LedgerEntryType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Attendance;
use App\Models\DailyClosing;
use App\Models\Employee;
use App\Models\Material;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\User;
use App\Services\CashService;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

it('redirects a guest to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows a user with no role a page rather than a broken screen', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cash', null)
            ->where('orders', null)
            ->where('payable', null)
            ->where('labour', null)
            ->where('stock', null)
            ->where('today.attendance', null)
            ->where('today.closing', null)
            ->where('today.late_orders', null)
        );
});

it('adds up what the drawers hold', function () {
    Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 40000,
        'current_balance' => 40000,
    ]);
    Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 10000,
        'current_balance' => 10000,
    ]);
    // Real money, but not in the box. Same rule the daily closing uses.
    Account::factory()->create([
        'type' => AccountType::MobileBanking,
        'opening_balance' => 25000,
        'current_balance' => 25000,
    ]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('cash.cash_in_hand', '50000.00'));
});

it('shows what moved today and leaves yesterday out', function () {
    $drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 50000,
        'current_balance' => 50000,
    ]);

    $cash = app(CashService::class);
    $today = now()->toDateString();

    $cash->record($drawer, TransactionDirection::In, '12000', $today, TransactionSource::Sale);
    $cash->record($drawer, TransactionDirection::Out, '3000', $today, TransactionSource::Expense);
    $cash->record($drawer, TransactionDirection::In, '9999', now()->subDay()->toDateString(), TransactionSource::Sale);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('cash.today_in', '12000.00')
            ->where('cash.today_out', '3000.00')
        );
});

it('adds up what customers still owe on open jobs', function () {
    Order::factory()->confirmed()->withTotals('50000.00', '20000.00')->create();
    Order::factory()->confirmed()->withTotals('10000.00')->create();
    // Finished, so it is nobody's outstanding work.
    Order::factory()->confirmed()->withTotals('8000.00')->create(['status' => OrderStatus::Delivered]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('orders.receivable', '40000.00')
            ->where('orders.open_orders', 2)
        );
});

it('counts deliveries promised this week and the ones already late', function () {
    Order::factory()->confirmed()->create(['expected_delivery_date' => now()->addDays(3)->toDateString()]);
    Order::factory()->confirmed()->create(['expected_delivery_date' => now()->addDays(20)->toDateString()]);
    Order::factory()->confirmed()->create(['expected_delivery_date' => now()->subDays(2)->toDateString()]);
    // Delivered late is not still late.
    Order::factory()->confirmed()->create([
        'status' => OrderStatus::Delivered,
        'expected_delivery_date' => now()->subDays(5)->toDateString(),
    ]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('orders.due_this_week', 1)
            ->where('orders.late_delivery', 1)
        );
});

it('adds up what we owe suppliers and flags the old challans', function () {
    Purchase::factory()->onCredit('12000.00', now()->subDays(5)->toDateString())->create();
    Purchase::factory()->onCredit('8000.00', now()->addDays(10)->toDateString())->create();
    Purchase::factory()->withTotals('5000.00', '5000.00')->create();

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('payable.payable', '20000.00')
            ->where('payable.owing_challans', 2)
            ->where('payable.overdue_challans', 1)
        );
});

/**
 * A worker who has drawn more than they earned owes the shop. Netting that off
 * would understate the wage bill waiting to be paid.
 */
it('counts only the workers who are owed', function () {
    $ledger = app(LedgerService::class);

    $owed = Employee::factory()->create();
    $overdrawn = Employee::factory()->create();

    $ledger->record($owed, LedgerEntryType::WageEarned, '5000.00', '2026-07-20');
    $ledger->record($overdrawn, LedgerEntryType::WageEarned, '2000.00', '2026-07-20');
    $ledger->record($overdrawn, LedgerEntryType::Advance, '3000.00', '2026-07-21');

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('labour.worker_dues', '5000.00')
            ->where('labour.workers_owed', 1)
        );
});

it('counts what has fallen to its reorder line', function () {
    Material::factory()->inStock('4.000')->create(['min_stock' => '5.000']);
    Material::factory()->inStock('50.000')->create(['min_stock' => '5.000']);
    // Switched off, so nobody is going to buy more of it.
    Material::factory()->inStock('1.000')->create(['min_stock' => '5.000', 'is_active' => false]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('stock.low_stock', 1));
});

it('reads zero for a shop with nothing in it yet', function () {
    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cash.cash_in_hand', '0.00')
            ->where('orders.receivable', '0.00')
            ->where('payable.payable', '0.00')
            ->where('labour.worker_dues', '0.00')
            ->where('stock.low_stock', 0)
        );
});

/**
 * A block the reader has no permission for is not computed and not sent,
 * rather than sent and hidden.
 */
it('sends a storekeeper no cash or payable figures', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 50000,
        'current_balance' => 50000,
    ]);
    Purchase::factory()->onCredit('12000.00')->create();

    $this->actingAs($storekeeper)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('cash', null)
            ->where('payable', null)
            ->where('labour', null)
            ->has('orders')
            ->has('stock')
        );
});

it('sends an accountant the money figures but nothing from the store room', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('cash')
            ->has('payable')
            ->has('labour')
            ->where('stock', null)
        );
});

it('sends a manager the order figures but not the drawer', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('orders')
            ->has('payable')
            ->has('labour')
            ->has('stock')
            // transactions.view is the bookkeeper's, not the manager's.
            ->where('cash', null)
        );
});

it('counts today\'s attendance against active workers only', function () {
    $today = now()->toDateString();

    $marked = Employee::factory()->count(2)->create();
    Employee::factory()->create(); // active, not yet marked
    $turnedOff = Employee::factory()->create(['is_active' => false]);
    $gone = Employee::factory()->create();

    foreach ($marked as $employee) {
        Attendance::factory()->create(['employee_id' => $employee->id, 'work_date' => $today]);
    }
    // Marked earlier, then turned off or removed: neither may push marked past total.
    Attendance::factory()->create(['employee_id' => $turnedOff->id, 'work_date' => $today]);
    Attendance::factory()->create(['employee_id' => $gone->id, 'work_date' => $today]);
    $gone->delete();
    // Yesterday's mark is not today's.
    Attendance::factory()->create(['employee_id' => $marked[0]->id, 'work_date' => now()->subDay()->toDateString()]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today.attendance.marked', 2)
            ->where('today.attendance.total', 3)
        );
});

it('shows every active shop\'s closing to a reader with no shop of their own', function () {
    $closed = Shop::factory()->create(['name' => 'A Shop']);
    $open = Shop::factory()->create(['name' => 'B Shop']);
    Shop::factory()->create(['name' => 'C Shop', 'is_active' => false]);

    DailyClosing::factory()->create(['shop_id' => $closed->id, 'closing_date' => now()->toDateString(), 'closed_at' => now()]);
    // A row with no closed_at is a started day, not a closed one.
    DailyClosing::factory()->create(['shop_id' => $open->id, 'closing_date' => now()->toDateString(), 'closed_at' => null]);
    // Yesterday's closing does not close today.
    DailyClosing::factory()->create(['shop_id' => $open->id, 'closing_date' => now()->subDay()->toDateString(), 'closed_at' => now()->subDay()]);

    $this->owner->update(['shop_id' => null]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.closing', 2)
            ->where('today.closing.0.shop_id', $closed->id)
            ->where('today.closing.0.closed', true)
            ->where('today.closing.1.shop_id', $open->id)
            ->where('today.closing.1.closed', false)
        );
});

it('shows only the reader\'s own shop, even when that shop is switched off', function () {
    Shop::factory()->create(['name' => 'A Shop']);
    $mine = Shop::factory()->create(['name' => 'B Shop', 'is_active' => false]);

    $this->owner->update(['shop_id' => $mine->id]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.closing', 1)
            ->where('today.closing.0.shop_id', $mine->id)
            ->where('today.closing.0.closed', false)
        );
});

it('lists the five most overdue open orders, oldest first', function () {
    foreach ([1, 2, 3, 4, 5, 6] as $days) {
        Order::factory()->confirmed()->create(['expected_delivery_date' => now()->subDays($days)->toDateString()]);
    }
    Order::factory()->confirmed()->create(['status' => OrderStatus::Delivered, 'expected_delivery_date' => now()->subDays(30)->toDateString()]);
    Order::factory()->confirmed()->create(['status' => OrderStatus::Cancelled, 'expected_delivery_date' => now()->subDays(40)->toDateString()]);
    // Due today is not late yet.
    Order::factory()->confirmed()->create(['expected_delivery_date' => now()->toDateString()]);

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.late_orders', 5)
            ->where('today.late_orders.0.days_late', 6)
            ->where('today.late_orders.4.days_late', 2)
            ->has('today.late_orders.0.customer_name')
        );
});

it('lists a late draft order without a number', function () {
    Order::factory()->create(['expected_delivery_date' => now()->subDays(3)->toDateString()]); // draft, order_no null

    $this->actingAs($this->owner)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('today.late_orders', 1)
            ->where('today.late_orders.0.order_no', null)
            ->where('today.late_orders.0.days_late', 3)
        );
});

it('leaves out the today blocks a storekeeper may not see', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('today.attendance', null)
            ->where('today.closing', null)
            ->where('today.late_orders', [])
        );
});
