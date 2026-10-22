<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;

/**
 * Records one day of attendance for a set of employees.
 *
 * This runs more than once against the same date. The shop marks the morning,
 * corrects someone at noon, and saves again. Every save must leave the same
 * number of rows behind as the first one did:
 *
 * - attendance is upserted on (employee_id, work_date)
 * - the wage credit is tied to its attendance row and reconciled, never added
 *
 * Wage rules by employee wage_type:
 * - daily:   present writes a full daily_rate credit, half_day writes half
 * - monthly: the day is still recorded, but the credit lands once at month end
 * - piece:   earnings come from completed work, not from showing up
 *
 * absent, leave and holiday earn nothing and leave no ledger row at all.
 */
class MarkDailyAttendance
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  list<array{employee_id: int, status: string, in_time?: ?string, out_time?: ?string, overtime_hours?: ?float, overtime_rate?: ?float, note?: ?string}>  $rows
     * @return list<Attendance>
     */
    public function handle(string $workDate, array $rows, ?int $markedBy = null, ?int $shopId = null): array
    {
        return DB::transaction(function () use ($workDate, $rows, $markedBy, $shopId) {
            $employees = Employee::query()
                ->whereIn('id', array_column($rows, 'employee_id'))
                ->get()
                ->keyBy('id');

            $saved = [];

            foreach ($rows as $row) {
                $employee = $employees->get($row['employee_id']);

                if ($employee === null) {
                    continue;
                }

                $status = AttendanceStatus::from($row['status']);

                $attendance = Attendance::updateOrCreate(
                    ['employee_id' => $employee->id, 'work_date' => $workDate],
                    [
                        'shop_id' => $shopId ?? $employee->shop_id,
                        'status' => $status,
                        'in_time' => $row['in_time'] ?? null,
                        'out_time' => $row['out_time'] ?? null,
                        'overtime_hours' => $row['overtime_hours'] ?? 0,
                        'overtime_rate' => $row['overtime_rate'] ?? 0,
                        'note' => $row['note'] ?? null,
                        'marked_by' => $markedBy,
                    ]
                );

                $this->syncWage($employee, $attendance, $status, $workDate, $markedBy);
                $this->syncNight($employee, $attendance, $status, $workDate, $markedBy);
                $this->syncOvertime($employee, $attendance, $workDate, $markedBy);

                $saved[] = $attendance;
            }

            return $saved;
        });
    }

    private function syncWage(
        Employee $employee,
        Attendance $attendance,
        AttendanceStatus $status,
        string $workDate,
        ?int $markedBy,
    ): void {
        // Monthly staff are paid once at month end; piece workers are paid for
        // finished work. Neither earns anything by being marked present.
        $amount = $employee->wage_type === WageType::Daily
            ? bcmul((string) $employee->daily_rate, $status->wageFraction(), 2)
            : '0.00';

        // Always sync, even at zero. Flipping present to absent has to remove
        // yesterday's credit, not leave it sitting there.
        $this->ledger->syncForReference(
            employee: $employee,
            type: LedgerEntryType::WageEarned,
            amount: $amount,
            entryDate: $workDate,
            reference: $attendance,
            note: $status->label(),
            createdBy: $markedBy,
        );
    }

    /**
     * The night allowance, kept as its own credit so it is never folded into
     * the day wage and never counted twice. Daily and piece workers earn it;
     * monthly staff are on a fixed salary and earn nothing extra for a night.
     *
     * The amount is the employee's flat night_rate when one is set, otherwise a
     * configurable fraction of the daily wage (half by default). Syncing even
     * at zero is what lets correcting a night shift back to a plain day take
     * the allowance back out.
     */
    private function syncNight(
        Employee $employee,
        Attendance $attendance,
        AttendanceStatus $status,
        string $workDate,
        ?int $markedBy,
    ): void {
        $this->ledger->syncForReference(
            employee: $employee,
            type: LedgerEntryType::NightAllowance,
            amount: $this->nightAllowanceFor($employee, $status),
            entryDate: $workDate,
            reference: $attendance,
            note: $status->label(),
            createdBy: $markedBy,
        );
    }

    private function nightAllowanceFor(Employee $employee, AttendanceStatus $status): string
    {
        $eligible = in_array($employee->wage_type, [WageType::Daily, WageType::Piece], true);

        if (! $status->hasNight() || ! $eligible) {
            return '0.00';
        }

        if ($employee->night_rate !== null) {
            return bcadd((string) $employee->night_rate, '0', 2);
        }

        $fraction = Setting::read('payroll.night_multiplier', '0.5');

        return bcmul((string) $employee->daily_rate, $fraction, 2);
    }

    private function syncOvertime(Employee $employee, Attendance $attendance, string $workDate, ?int $markedBy): void
    {
        $amount = bcmul((string) $attendance->overtime_hours, (string) $attendance->overtime_rate, 2);

        $this->ledger->syncForReference(
            employee: $employee,
            type: LedgerEntryType::Overtime,
            amount: $amount,
            entryDate: $workDate,
            reference: $attendance,
            createdBy: $markedBy,
        );
    }
}
