<?php

use App\Actions\Attendance\MarkDailyAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;

const WORK_DATE = '2026-07-20';

function markAttendance(array $rows, string $date = WORK_DATE): array
{
    return app(MarkDailyAttendance::class)->handle($date, $rows);
}

it('writes one attendance row and one wage credit for a present daily worker', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([['employee_id' => $employee->id, 'status' => 'present']]);

    expect(Attendance::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(1);

    $entry = EmployeeLedger::sole();

    expect($entry->type)->toBe(LedgerEntryType::WageEarned)
        ->and($entry->direction)->toBe(LedgerDirection::Credit)
        ->and($entry->amount)->toBe('700.00')
        ->and($entry->reference_type)->toBe(Attendance::class)
        ->and($entry->reference_id)->toBe(Attendance::sole()->id);
});

it('credits half a day for a half day', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 705]);

    markAttendance([['employee_id' => $employee->id, 'status' => 'half_day']]);

    expect(EmployeeLedger::sole()->amount)->toBe('352.50');
});

/**
 * absent, leave and holiday account for the day but earn nothing. A ledger row
 * here would be a day's wage paid for not working.
 */
it('writes no ledger row for a status that earns nothing', function (string $status) {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([['employee_id' => $employee->id, 'status' => $status]]);

    expect(Attendance::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0);
})->with(['absent', 'leave', 'holiday']);

it('records the day but no daily credit for a monthly worker', function () {
    $employee = Employee::factory()->monthly()->create();

    markAttendance([['employee_id' => $employee->id, 'status' => 'present']]);

    expect(Attendance::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('records the day but no daily credit for a piece worker', function () {
    $employee = Employee::factory()->piece()->create();

    markAttendance([['employee_id' => $employee->id, 'status' => 'present']]);

    expect(Attendance::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0);
});

/**
 * The case the whole design exists to protect. The shop marks the morning and
 * saves again after lunch; the second save must not pay anyone twice.
 */
it('is idempotent, so saving the same date twice changes no counts', function () {
    $employees = Employee::factory()->count(3)->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    $rows = $employees->map(fn ($e) => ['employee_id' => $e->id, 'status' => 'present'])->all();

    markAttendance($rows);

    $attendanceCount = Attendance::count();
    $ledgerCount = EmployeeLedger::count();
    $total = EmployeeLedger::sum('amount');

    markAttendance($rows);

    expect(Attendance::count())->toBe($attendanceCount)
        ->and(EmployeeLedger::count())->toBe($ledgerCount)
        ->and(EmployeeLedger::sum('amount'))->toEqual($total);
});

it('replaces the credit rather than adding one when a status is corrected', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([['employee_id' => $employee->id, 'status' => 'present']]);
    markAttendance([['employee_id' => $employee->id, 'status' => 'half_day']]);

    expect(EmployeeLedger::count())->toBe(1)
        ->and(EmployeeLedger::sole()->amount)->toBe('350.00')
        ->and(Attendance::sole()->status)->toBe(AttendanceStatus::HalfDay);
});

/**
 * Marking someone present and then correcting them to absent has to take the
 * wage back, not leave it sitting in the ledger.
 */
it('removes the credit when a present day is corrected to absent', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([['employee_id' => $employee->id, 'status' => 'present']]);
    expect(EmployeeLedger::count())->toBe(1);

    markAttendance([['employee_id' => $employee->id, 'status' => 'absent']]);

    expect(EmployeeLedger::count())->toBe(0)
        ->and(Attendance::count())->toBe(1)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('0.00');
});

it('keeps separate rows for separate dates', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([['employee_id' => $employee->id, 'status' => 'present']], '2026-07-20');
    markAttendance([['employee_id' => $employee->id, 'status' => 'present']], '2026-07-21');

    expect(Attendance::count())->toBe(2)
        ->and(EmployeeLedger::count())->toBe(2)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('1400.00');
});

it('writes a separate overtime credit alongside the wage', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([[
        'employee_id' => $employee->id,
        'status' => 'present',
        'overtime_hours' => 2.5,
        'overtime_rate' => 90,
    ]]);

    expect(EmployeeLedger::count())->toBe(2);

    $overtime = EmployeeLedger::where('type', LedgerEntryType::Overtime)->sole();

    expect($overtime->amount)->toBe('225.00')
        ->and($overtime->direction)->toBe(LedgerDirection::Credit)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('925.00');
});

it('drops the overtime credit when the hours are cleared', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([['employee_id' => $employee->id, 'status' => 'present', 'overtime_hours' => 2, 'overtime_rate' => 100]]);
    expect(EmployeeLedger::count())->toBe(2);

    markAttendance([['employee_id' => $employee->id, 'status' => 'present', 'overtime_hours' => 0, 'overtime_rate' => 0]]);

    expect(EmployeeLedger::count())->toBe(1)
        ->and(app(LedgerService::class)->balanceFor($employee))->toBe('700.00');
});

it('marks a whole crew in one go', function () {
    $crew = Employee::factory()->count(5)->create(['wage_type' => WageType::Daily, 'daily_rate' => 600]);

    markAttendance($crew->map(fn ($e) => ['employee_id' => $e->id, 'status' => 'present'])->all());

    expect(Attendance::count())->toBe(5)
        ->and(EmployeeLedger::count())->toBe(5)
        ->and(EmployeeLedger::sum('amount'))->toEqual(3000);
});

it('ignores an employee id that does not exist', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    markAttendance([
        ['employee_id' => $employee->id, 'status' => 'present'],
        ['employee_id' => 999999, 'status' => 'present'],
    ]);

    expect(Attendance::count())->toBe(1);
});

/**
 * Definition of done, clause 4: an exception mid-way persists nothing.
 */
it('persists nothing when the save fails part way through', function () {
    $good = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);
    $bad = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    expect(fn () => markAttendance([
        ['employee_id' => $good->id, 'status' => 'present'],
        ['employee_id' => $bad->id, 'status' => 'not-a-real-status'],
    ]))->toThrow(ValueError::class);

    expect(Attendance::count())->toBe(0)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('stamps who marked the attendance', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);
    $user = User::factory()->create();

    app(MarkDailyAttendance::class)->handle(
        WORK_DATE,
        [['employee_id' => $employee->id, 'status' => 'present']],
        markedBy: $user->id,
    );

    expect(Attendance::sole()->marked_by)->toBe($user->id)
        ->and(EmployeeLedger::sole()->created_by)->toBe($user->id);
});

it('keeps the balance exact to the paisa across a month of odd rates', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 333.33]);

    foreach (range(1, 30) as $day) {
        markAttendance(
            [['employee_id' => $employee->id, 'status' => 'present']],
            sprintf('2026-06-%02d', $day)
        );
    }

    // 30 x 333.33, computed in SQL, not accumulated as floats.
    expect(app(LedgerService::class)->balanceFor($employee))->toBe('9999.90')
        ->and(DB::table('employee_ledger')->count())->toBe(30);
});
