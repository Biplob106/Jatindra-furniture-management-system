<?php

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Enums\WageType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Shop;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    Carbon::setTestNow('2026-07-20 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('opens on today when no date is given', function () {
    $this->actingAs($this->manager)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('workDate', '2026-07-20'));
});

it('pulls a future date back to today rather than refusing it', function () {
    $this->actingAs($this->manager)
        ->get('/attendance?date=2027-01-01')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('workDate', '2026-07-20'));
});

it('falls back to today when the date is nonsense', function () {
    $this->actingAs($this->manager)
        ->get('/attendance?date=not-a-date')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('workDate', '2026-07-20'));
});

it('lists only active employees', function () {
    Employee::factory()->create(['name' => 'সক্রিয়', 'is_active' => true]);
    Employee::factory()->create(['name' => 'বন্ধ', 'is_active' => false]);

    $this->actingAs($this->manager)
        ->get('/attendance')
        ->assertInertia(fn ($page) => $page->has('employees', 1)->where('employees.0.name', 'সক্রিয়'));
});

it('filters the crew by shop', function () {
    $shop = Shop::factory()->create();
    Employee::factory()->create(['name' => 'এই দোকানের', 'shop_id' => $shop->id]);
    Employee::factory()->create(['name' => 'অন্য দোকানের', 'shop_id' => null]);

    $this->actingAs($this->manager)
        ->get("/attendance?shop_id={$shop->id}")
        ->assertInertia(fn ($page) => $page->has('employees', 1)->where('employees.0.name', 'এই দোকানের'));
});

/**
 * Re-opening the screen has to show the day as it stands, otherwise a
 * correction turns into a blank sheet that wipes what was marked.
 */
it('shows a saved day as it stands rather than blank', function () {
    $employee = Employee::factory()->create();

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'work_date' => '2026-07-20',
        'status' => AttendanceStatus::HalfDay,
        'overtime_hours' => 2,
        'overtime_rate' => 80,
    ]);

    $this->actingAs($this->manager)
        ->get('/attendance?date=2026-07-20')
        ->assertInertia(fn ($page) => $page
            ->where('employees.0.status', 'half_day')
            ->where('employees.0.overtime_hours', '2.00')
            ->where('alreadySaved', true)
        );
});

it('reports an unsaved day as unsaved', function () {
    Employee::factory()->create();

    $this->actingAs($this->manager)
        ->get('/attendance')
        ->assertInertia(fn ($page) => $page->where('alreadySaved', false)->where('employees.0.status', null));
});

it('saves the sheet and writes the wage credits', function () {
    $first = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);
    $second = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 500]);

    $this->actingAs($this->manager)
        ->post('/attendance', [
            'work_date' => '2026-07-20',
            'rows' => [
                ['employee_id' => $first->id, 'status' => 'present'],
                ['employee_id' => $second->id, 'status' => 'half_day'],
            ],
        ])
        ->assertRedirect();

    expect(Attendance::count())->toBe(2)
        ->and(EmployeeLedger::count())->toBe(2)
        ->and(app(LedgerService::class)->balanceFor($first))->toBe('700.00')
        ->and(app(LedgerService::class)->balanceFor($second))->toBe('250.00');
});

it('stamps who marked it', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($this->manager)->post('/attendance', [
        'work_date' => '2026-07-20',
        'rows' => [['employee_id' => $employee->id, 'status' => 'present']],
    ]);

    expect(Attendance::sole()->marked_by)->toBe($this->manager->id);
});

/**
 * The screen is built to be saved more than once a day. Going through the
 * controller twice must not pay anyone twice.
 */
it('stays idempotent through the controller', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    $payload = [
        'work_date' => '2026-07-20',
        'rows' => [['employee_id' => $employee->id, 'status' => 'present']],
    ];

    $this->actingAs($this->manager)->post('/attendance', $payload);
    $this->actingAs($this->manager)->post('/attendance', $payload);

    expect(Attendance::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(1)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('700.00');
});

it('refuses a future date', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($this->manager)
        ->post('/attendance', [
            'work_date' => '2026-07-21',
            'rows' => [['employee_id' => $employee->id, 'status' => 'present']],
        ])
        ->assertSessionHasErrors('work_date');

    expect(Attendance::count())->toBe(0);
});

it('refuses an unknown status and writes nothing', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($this->manager)
        ->post('/attendance', [
            'work_date' => '2026-07-20',
            'rows' => [['employee_id' => $employee->id, 'status' => 'maybe']],
        ])
        ->assertSessionHasErrors('rows.0.status');

    expect(Attendance::count())->toBe(0)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('refuses overtime beyond a day', function () {
    $employee = Employee::factory()->create();

    $this->actingAs($this->manager)
        ->post('/attendance', [
            'work_date' => '2026-07-20',
            'rows' => [['employee_id' => $employee->id, 'status' => 'present', 'overtime_hours' => 30, 'overtime_rate' => 50]],
        ])
        ->assertSessionHasErrors('rows.0.overtime_hours');
});

it('saves overtime as its own credit', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    $this->actingAs($this->manager)->post('/attendance', [
        'work_date' => '2026-07-20',
        'rows' => [['employee_id' => $employee->id, 'status' => 'present', 'overtime_hours' => 3, 'overtime_rate' => 100]],
    ]);

    expect(EmployeeLedger::count())->toBe(2)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('1000.00');
});

it('keeps the date and shop when it redirects back', function () {
    $shop = Shop::factory()->create();
    $employee = Employee::factory()->create(['shop_id' => $shop->id]);

    $this->actingAs($this->manager)
        ->post('/attendance', [
            'work_date' => '2026-07-19',
            'shop_id' => $shop->id,
            'rows' => [['employee_id' => $employee->id, 'status' => 'present']],
        ])
        ->assertRedirect(route('attendance.index', ['date' => '2026-07-19', 'shop_id' => $shop->id]));
});

it('lets an accountant read the sheet but not mark it', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $employee = Employee::factory()->create();

    $this->actingAs($accountant)
        ->get('/attendance')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('canMark', false));

    $this->actingAs($accountant)
        ->post('/attendance', [
            'work_date' => '2026-07-20',
            'rows' => [['employee_id' => $employee->id, 'status' => 'present']],
        ])
        ->assertForbidden();

    expect(Attendance::count())->toBe(0);
});

it('keeps a storekeeper out entirely', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/attendance')->assertForbidden();
});
