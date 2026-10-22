<?php

use App\Actions\Attendance\MarkDailyAttendance;
use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Employee;
use App\Queries\EmployeeMonthSummary;
use App\Services\LedgerService;

function mark(Employee $employee, string $status, string $date): void
{
    app(MarkDailyAttendance::class)->handle($date, [['employee_id' => $employee->id, 'status' => $status]]);
}

/**
 * The attendance side of the employee detail: how many of each kind of day the
 * worker put in over the month, and a single worked-days figure that counts a
 * half day as half and a night-only day as no day worked.
 */
it('counts the days worked by kind over the month', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    mark($employee, 'present', '2026-07-05');
    mark($employee, 'present', '2026-07-06');
    mark($employee, 'half_day', '2026-07-07');
    mark($employee, 'full_day_night', '2026-07-08');
    mark($employee, 'night_only', '2026-07-09');
    mark($employee, 'absent', '2026-07-10');
    mark($employee, 'present', '2026-06-30'); // previous month, must not count

    $summary = app(EmployeeMonthSummary::class)->attendanceFor($employee, '2026-07-01', '2026-07-31');

    expect($summary['present'])->toBe(2)
        ->and($summary['half_day'])->toBe(1)
        ->and($summary['full_day_night'])->toBe(1)
        ->and($summary['night_only'])->toBe(1)
        ->and($summary['absent'])->toBe(1)
        ->and($summary['nights'])->toBe(2)           // full_day_night + night_only
        ->and($summary['worked_days'])->toBe('3.5'); // 2 + 0.5 + 1 (night_only earns no day)
});

/**
 * The money side of the detail, each kind on its own line so advance and
 * tiffin can be read apart from a plain payout.
 */
it('breaks the month money down by kind', function () {
    $employee = Employee::factory()->create(['daily_rate' => 700]);
    $ledger = app(LedgerService::class);

    $ledger->record($employee, LedgerEntryType::WageEarned, '700.00', '2026-07-10');
    $ledger->record($employee, LedgerEntryType::NightAllowance, '350.00', '2026-07-10');
    $ledger->record($employee, LedgerEntryType::Advance, '300.00', '2026-07-11');
    $ledger->record($employee, LedgerEntryType::Tiffin, '100.00', '2026-07-12');
    $ledger->record($employee, LedgerEntryType::Payout, '1000.00', '2026-07-13');
    $ledger->record($employee, LedgerEntryType::Fine, '50.00', '2026-07-14');
    $ledger->record($employee, LedgerEntryType::WageEarned, '700.00', '2026-06-30'); // other month

    $money = app(EmployeeMonthSummary::class)->moneyFor($employee, '2026-07-01', '2026-07-31');

    expect($money['earned'])->toBe('1050.00')   // wage + night, this month only
        ->and($money['night'])->toBe('350.00')
        ->and($money['advance'])->toBe('300.00')
        ->and($money['tiffin'])->toBe('100.00')
        ->and($money['payout'])->toBe('1000.00')
        ->and($money['deductions'])->toBe('50.00'); // fine + custom deduction
});

it('reports zeroes for a month with no attendance', function () {
    $employee = Employee::factory()->create();

    $summary = app(EmployeeMonthSummary::class)->attendanceFor($employee, '2026-07-01', '2026-07-31');

    expect($summary['present'])->toBe(0)
        ->and($summary['nights'])->toBe(0)
        ->and($summary['worked_days'])->toBe('0.0');
});
