<?php

use App\Enums\LedgerEntryType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->accountant = User::factory()->create();
    $this->accountant->assignRole(Role::Accountant->value);

    $this->employee = Employee::factory()->create(['name' => 'সুমন মিস্ত্রি']);
    $this->account = Account::factory()->create(['opening_balance' => 50000, 'current_balance' => 50000]);

    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '5000.00', '2026-07-01');

    Carbon::setTestNow('2026-07-20 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists workers with what each is owed', function () {
    $this->actingAs($this->accountant)
        ->get('/employee-ledger')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employees.0.name', 'সুমন মিস্ত্রি')
            ->where('employees.0.balance', '5000.00')
        );
});

/**
 * The person owed most is who asks first, so they belong at the top.
 */
it('puts the biggest balance first', function () {
    $poorer = Employee::factory()->create(['name' => 'কম পাওনা']);
    $richer = Employee::factory()->create(['name' => 'বেশি পাওনা']);

    app(LedgerService::class)->record($poorer, LedgerEntryType::WageEarned, '100.00', '2026-07-01');
    app(LedgerService::class)->record($richer, LedgerEntryType::WageEarned, '90000.00', '2026-07-01');

    $this->actingAs($this->accountant)
        ->get('/employee-ledger')
        ->assertInertia(fn ($page) => $page->where('employees.0.name', 'বেশি পাওনা'));
});

/**
 * A worker who has over-drawn is owed nothing; counting their negative would
 * understate what the shop actually has to find on payday.
 */
it('leaves negative balances out of the total owed', function () {
    $overdrawn = Employee::factory()->create();
    app(LedgerService::class)->record($overdrawn, LedgerEntryType::Advance, '3000.00', '2026-07-01');

    $this->actingAs($this->accountant)
        ->get('/employee-ledger')
        ->assertInertia(fn ($page) => $page->where('totalOwed', '5000.00'));
});

it('shows one worker with their entries and totals', function () {
    $this->actingAs($this->accountant)
        ->get("/employee-ledger/{$this->employee->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('balance', '5000.00')
            ->where('totals.earned', '5000.00')
            ->where('totals.taken', '0.00')
            ->has('entries.data', 1)
        );
});

it('records a payout, writing both ledgers', function () {
    $this->actingAs($this->accountant)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'payout',
            'amount' => '2000',
            'entry_date' => '2026-07-20',
            'account_id' => $this->account->id,
            'payment_method' => 'cash',
        ])
        ->assertRedirect(route('employee-ledger.show', $this->employee));

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('3000.00')
        ->and($this->account->fresh()->current_balance)->toBe('48000.00');
});

it('records a fine without touching the cash box', function () {
    $this->actingAs($this->accountant)->post("/employee-ledger/{$this->employee->id}", [
        'type' => 'fine',
        'amount' => '500',
        'entry_date' => '2026-07-20',
    ]);

    expect(Transaction::count())->toBe(0)
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('4500.00')
        ->and($this->account->fresh()->current_balance)->toBe('50000.00');
});

it('demands an account when money actually changes hands', function () {
    $this->actingAs($this->accountant)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'payout',
            'amount' => '2000',
            'entry_date' => '2026-07-20',
        ])
        ->assertSessionHasErrors('account_id');

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->count())->toBe(0);
});

it('refuses a type that is not a payment', function () {
    $this->actingAs($this->accountant)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'wage_earned',
            'amount' => '2000',
            'entry_date' => '2026-07-20',
        ])
        ->assertSessionHasErrors('type');
});

it('refuses a zero or negative amount', function (string $amount) {
    $this->actingAs($this->accountant)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'fine',
            'amount' => $amount,
            'entry_date' => '2026-07-20',
        ])
        ->assertSessionHasErrors('amount');

    expect(EmployeeLedger::where('type', LedgerEntryType::Fine)->count())->toBe(0);
})->with(['0', '-500']);

it('refuses a future date', function () {
    $this->actingAs($this->accountant)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'fine',
            'amount' => '100',
            'entry_date' => '2026-07-21',
        ])
        ->assertSessionHasErrors('entry_date');
});

/**
 * The drawer refusing is not a validation failure, it is a runtime one. It has
 * to reach the user as a readable message with nothing written.
 */
it('reports a Bengali message when the drawer cannot cover the payment', function () {
    $small = Account::factory()->create(['opening_balance' => 100, 'current_balance' => 100]);

    $this->actingAs($this->accountant)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'payout',
            'amount' => '2000',
            'entry_date' => '2026-07-20',
            'account_id' => $small->id,
        ])
        ->assertSessionHas('error');

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($small->fresh()->current_balance)->toBe('100.00');
});

it('keeps paisa precision through the form', function () {
    $this->actingAs($this->accountant)->post("/employee-ledger/{$this->employee->id}", [
        'type' => 'payout',
        'amount' => '1234.56',
        'entry_date' => '2026-07-20',
        'account_id' => $this->account->id,
    ]);

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->sole()->amount)->toBe('1234.56')
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('3765.44');
});

it('stamps who recorded the payment', function () {
    $this->actingAs($this->accountant)->post("/employee-ledger/{$this->employee->id}", [
        'type' => 'payout',
        'amount' => '1000',
        'entry_date' => '2026-07-20',
        'account_id' => $this->account->id,
    ]);

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->sole()->created_by)->toBe($this->accountant->id);
});

it('lets a manager pay, since the seeder grants them that', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)
        ->get('/employee-ledger')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canPay', true));
});

/**
 * Reading a balance and handing over money are separate permissions. No seeded
 * role holds only the first today, but the split has to hold on its own rather
 * than depending on that staying true.
 */
it('hides the payment form and refuses the post for view-only access', function () {
    // Revoked from the role, not the user: a user-level revoke does not
    // override a permission the role grants.
    SpatieRole::findByName(Role::Manager->value)->revokePermissionTo('employee_payment.record');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $viewer = User::factory()->create();
    $viewer->assignRole(Role::Manager->value);

    $this->actingAs($viewer)
        ->get('/employee-ledger')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canPay', false));

    $this->actingAs($viewer)
        ->post("/employee-ledger/{$this->employee->id}", [
            'type' => 'fine',
            'amount' => '100',
            'entry_date' => '2026-07-20',
        ])
        ->assertForbidden();

    expect(EmployeeLedger::where('type', LedgerEntryType::Fine)->count())->toBe(0);
});

it('keeps a storekeeper out entirely', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/employee-ledger')->assertForbidden();
});
