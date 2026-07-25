<?php

use App\Actions\Attendance\MarkDailyAttendance;
use App\Actions\Employees\GenerateMonthlySalary;
use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->action = app(GenerateMonthlySalary::class);
    $this->ledger = app(LedgerService::class);
});

it('credits one month of salary dated the last day of the month', function () {
    $employee = Employee::factory()->monthly()->create(['monthly_salary' => 18000]);

    $result = $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(1)
        ->and($result['credited'])->toBe(1)
        ->and($result['total'])->toBe('18000.00');

    $entry = EmployeeLedger::sole();

    expect($entry->type)->toBe(LedgerEntryType::WageEarned)
        ->and($entry->amount)->toBe('18000.00')
        ->and($entry->entry_date->toDateString())->toBe('2026-07-31')
        ->and($this->ledger->balanceFor($employee))->toBe('18000.00');
});

it('lands on the right last day for a short month and a leap February', function (string $month, string $expected) {
    Employee::factory()->monthly()->create(['monthly_salary' => 10000]);

    $this->action->handle($month);

    expect(EmployeeLedger::sole()->entry_date->toDateString())->toBe($expected);
})->with([
    'thirty day month' => ['2026-06', '2026-06-30'],
    'thirty one day month' => ['2026-07', '2026-07-31'],
    'february' => ['2026-02', '2026-02-28'],
    'leap february' => ['2028-02', '2028-02-29'],
]);

it('accepts any date inside the month, not only the first', function () {
    Employee::factory()->monthly()->create(['monthly_salary' => 10000]);

    $this->action->handle('2026-07-14');

    expect(EmployeeLedger::sole()->entry_date->toDateString())->toBe('2026-07-31');
});

/**
 * The idempotency case CLAUDE.md names. Somebody runs it on the 31st, then
 * again after a correction. Nobody may be paid twice.
 */
it('is idempotent, so a second run changes no counts', function () {
    Employee::factory()->count(3)->monthly()->create(['monthly_salary' => 18000]);

    $this->action->handle('2026-07');

    $count = EmployeeLedger::count();
    $total = EmployeeLedger::sum('amount');

    $second = $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe($count)
        ->and(EmployeeLedger::sum('amount'))->toEqual($total)
        ->and($second['credited'])->toBe(0)
        ->and($second['skipped'])->toBe(3);
});

/**
 * A salary corrected after the run should show the new figure, still on one
 * row. Adding a second row would pay the difference twice.
 */
it('corrects the amount in place when the salary changed', function () {
    $employee = Employee::factory()->monthly()->create(['monthly_salary' => 18000]);

    $this->action->handle('2026-07');

    $employee->update(['monthly_salary' => 20000]);
    $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(1)
        ->and(EmployeeLedger::sole()->amount)->toBe('20000.00')
        ->and($this->ledger->balanceFor($employee))->toBe('20000.00');
});

it('keeps separate months apart', function () {
    $employee = Employee::factory()->monthly()->create(['monthly_salary' => 15000]);

    $this->action->handle('2026-06');
    $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(2)
        ->and($this->ledger->balanceFor($employee))->toBe('30000.00');
});

it('pays nobody but monthly staff', function () {
    $daily = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);
    $piece = Employee::factory()->piece()->create();
    $monthly = Employee::factory()->monthly()->create(['monthly_salary' => 12000]);

    $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(1)
        ->and($this->ledger->balanceFor($monthly))->toBe('12000.00')
        ->and($this->ledger->balanceFor($daily))->toBe('0.00')
        ->and($this->ledger->balanceFor($piece))->toBe('0.00');
});

it('skips staff who have left', function () {
    Employee::factory()->monthly()->create(['monthly_salary' => 12000, 'is_active' => false]);

    $result = $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(0)
        ->and($result['credited'])->toBe(0);
});

it('skips a monthly worker with no salary on file rather than crediting zero', function () {
    Employee::factory()->monthly()->create(['monthly_salary' => 0]);

    $result = $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(0)
        ->and($result['credited'])->toBe(0)
        ->and($result['skipped'])->toBe(1);
});

/**
 * A daily worker present on the 31st has a wage_earned row on the same date.
 * The salary run must not mistake it for its own work, in either direction.
 */
it('does not confuse an attendance wage row with a salary row', function () {
    $monthly = Employee::factory()->monthly()->create(['monthly_salary' => 18000]);
    $daily = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    app(MarkDailyAttendance::class)->handle('2026-07-31', [
        ['employee_id' => $daily->id, 'status' => 'present'],
        ['employee_id' => $monthly->id, 'status' => 'present'],
    ]);

    // The daily worker earned a day; the monthly worker earned nothing yet.
    expect(EmployeeLedger::count())->toBe(1);

    $this->action->handle('2026-07');
    $this->action->handle('2026-07');

    expect(EmployeeLedger::count())->toBe(2)
        ->and($this->ledger->balanceFor($daily))->toBe('700.00')
        ->and($this->ledger->balanceFor($monthly))->toBe('18000.00');
});

it('holds to the paisa across a payroll of odd salaries', function () {
    foreach (['18333.33', '9999.99', '12500.01'] as $salary) {
        Employee::factory()->monthly()->create(['monthly_salary' => $salary]);
    }

    $result = $this->action->handle('2026-07');

    expect($result['total'])->toBe('40833.33')
        ->and(EmployeeLedger::sum('amount'))->toEqual(40833.33);
});

it('stamps who ran it', function () {
    $user = User::factory()->create();
    Employee::factory()->monthly()->create(['monthly_salary' => 10000]);

    $this->action->handle('2026-07', createdBy: $user->id);

    expect(EmployeeLedger::sole()->created_by)->toBe($user->id);
});

it('persists nothing when the run fails part way through', function () {
    Employee::factory()->count(2)->monthly()->create(['monthly_salary' => 10000]);

    expect(fn () => $this->action->handle('not-a-month'))->toThrow(Exception::class);

    expect(EmployeeLedger::count())->toBe(0);
});

it('runs from the command line and defaults to last month', function () {
    Carbon::setTestNow('2026-08-01 01:00:00');

    Employee::factory()->monthly()->create(['monthly_salary' => 16000]);

    $this->artisan('salary:generate')->assertSuccessful();

    expect(EmployeeLedger::sole()->entry_date->toDateString())->toBe('2026-07-31');

    Carbon::setTestNow();
});

it('takes an explicit month from the command line', function () {
    Employee::factory()->monthly()->create(['monthly_salary' => 16000]);

    $this->artisan('salary:generate', ['month' => '2026-05'])->assertSuccessful();

    expect(EmployeeLedger::sole()->entry_date->toDateString())->toBe('2026-05-31');
});

it('is safe to run twice from the command line', function () {
    Employee::factory()->monthly()->create(['monthly_salary' => 16000]);

    $this->artisan('salary:generate', ['month' => '2026-07'])->assertSuccessful();
    $this->artisan('salary:generate', ['month' => '2026-07'])->assertSuccessful();

    expect(EmployeeLedger::count())->toBe(1);
});
