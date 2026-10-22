<?php

use App\Actions\Attendance\MarkDailyAttendance;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Setting;
use App\Services\LedgerService;

const NIGHT_DATE = '2026-07-20';

/**
 * @param  list<array<string, mixed>>  $rows
 * @return list<Attendance>
 */
function markNight(array $rows, string $date = NIGHT_DATE): array
{
    return app(MarkDailyAttendance::class)->handle($date, $rows);
}

function nightRowFor(Employee $employee): ?EmployeeLedger
{
    return EmployeeLedger::query()
        ->where('employee_id', $employee->id)
        ->where('type', LedgerEntryType::NightAllowance)
        ->first();
}

/**
 * Full day + night is a full day's wage plus the night allowance, written as
 * two separate credits so neither can be double-counted against the other.
 */
it('writes a day wage and a separate night allowance for full day plus night', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markNight([['employee_id' => $employee->id, 'status' => 'full_day_night']]);

    expect(EmployeeLedger::count())->toBe(2)
        ->and(EmployeeLedger::where('type', LedgerEntryType::WageEarned)->sole()->amount)->toBe('700.00');

    $night = nightRowFor($employee);

    expect($night->amount)->toBe('350.00')
        ->and($night->direction)->toBe(LedgerDirection::Credit)
        ->and($night->reference_type)->toBe(Attendance::class)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('1050.00');
});

/**
 * Night only earns the allowance and nothing for the day — there is no day
 * wage row at all.
 */
it('writes only the night allowance for a night-only day', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markNight([['employee_id' => $employee->id, 'status' => 'night_only']]);

    expect(EmployeeLedger::count())->toBe(1)
        ->and(EmployeeLedger::where('type', LedgerEntryType::WageEarned)->count())->toBe(0)
        ->and(nightRowFor($employee)->amount)->toBe('350.00')
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('350.00');
});

/**
 * Half day + night: half the day's wage plus the full night allowance.
 */
it('writes half a day wage and a full night allowance for half day plus night', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markNight([['employee_id' => $employee->id, 'status' => 'half_day_night']]);

    expect(EmployeeLedger::where('type', LedgerEntryType::WageEarned)->sole()->amount)->toBe('350.00')
        ->and(nightRowFor($employee)->amount)->toBe('350.00')
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('700.00');
});

/**
 * Monthly staff are on a fixed salary, so a night shift adds no allowance for
 * them. The attendance is still recorded.
 */
it('writes no night allowance for a monthly worker', function () {
    $employee = Employee::factory()->monthly()->create();

    markNight([['employee_id' => $employee->id, 'status' => 'full_day_night']]);

    expect(Attendance::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0);
});

/**
 * Piece workers do earn the night allowance, but only when a rate is on file
 * for them, since they have no daily_rate to halve.
 */
it('writes a night allowance for a piece worker with a configured night rate', function () {
    $employee = Employee::factory()->piece()->create(['night_rate' => 250]);

    markNight([['employee_id' => $employee->id, 'status' => 'night_only']]);

    expect(nightRowFor($employee)->amount)->toBe('250.00')
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('250.00');
});

/**
 * A per-employee night rate overrides the half-of-daily-wage default.
 */
it('uses the employee night rate override instead of half the daily wage', function () {
    $employee = Employee::factory()->create([
        'wage_type' => WageType::Daily,
        'daily_rate' => 700,
        'night_rate' => 200,
    ]);

    markNight([['employee_id' => $employee->id, 'status' => 'full_day_night']]);

    expect(EmployeeLedger::where('type', LedgerEntryType::WageEarned)->sole()->amount)->toBe('700.00')
        ->and(nightRowFor($employee)->amount)->toBe('200.00')
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('900.00');
});

/**
 * The default allowance is half the daily wage, but the fraction is an
 * admin-configurable setting, not a hardcoded 0.5.
 */
it('reads the night multiplier from settings', function () {
    Setting::query()->create(['key' => 'payroll.night_multiplier', 'value' => '0.6', 'group' => 'payroll']);
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markNight([['employee_id' => $employee->id, 'status' => 'night_only']]);

    expect(nightRowFor($employee)->amount)->toBe('420.00');
});

/**
 * Saving the same night twice must not pay the allowance twice.
 */
it('is idempotent for the night allowance', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markNight([['employee_id' => $employee->id, 'status' => 'full_day_night']]);
    $count = EmployeeLedger::count();
    $balance = app(LedgerService::class)->balanceFor($employee);

    markNight([['employee_id' => $employee->id, 'status' => 'full_day_night']]);

    expect(EmployeeLedger::count())->toBe($count)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe($balance);
});

/**
 * Correcting a night shift back to a plain present day removes the allowance
 * rather than leaving it stranded in the ledger.
 */
it('removes the night allowance when a night shift is corrected to present', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markNight([['employee_id' => $employee->id, 'status' => 'full_day_night']]);
    expect(EmployeeLedger::count())->toBe(2);

    markNight([['employee_id' => $employee->id, 'status' => 'present']]);

    expect(EmployeeLedger::where('type', LedgerEntryType::NightAllowance)->count())->toBe(0)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('700.00');
});
