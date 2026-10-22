<?php

namespace App\Queries;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLedger;

/**
 * The attendance side of an employee's detail page: how many of each kind of
 * day they worked in a period, and a single worked-days figure.
 *
 * Worked days weight each status the same way the wage does: a full day (incl.
 * full-day-plus-night) is 1, a half day (incl. half-day-plus-night) is 0.5, and
 * a night-only day is 0 worked days — the night is counted separately under
 * `nights`, not as a day on the floor.
 */
class EmployeeMonthSummary
{
    /**
     * @return array{
     *     present: int, half_day: int, night_only: int, full_day_night: int,
     *     half_day_night: int, absent: int, leave: int, holiday: int,
     *     nights: int, worked_days: string
     * }
     */
    public function attendanceFor(Employee $employee, string $from, string $to): array
    {
        $counts = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$from, $to])
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS total')
            ->pluck('total', 'status');

        $of = fn (string $status): int => (int) ($counts[$status] ?? 0);

        $present = $of('present');
        $halfDay = $of('half_day');
        $nightOnly = $of('night_only');
        $fullDayNight = $of('full_day_night');
        $halfDayNight = $of('half_day_night');

        // Day-equivalents: full days count 1, half days 0.5, night-only 0.
        $workedDays = $present + $fullDayNight + ($halfDay + $halfDayNight) * 0.5;

        return [
            'present' => $present,
            'half_day' => $halfDay,
            'night_only' => $nightOnly,
            'full_day_night' => $fullDayNight,
            'half_day_night' => $halfDayNight,
            'absent' => $of('absent'),
            'leave' => $of('leave'),
            'holiday' => $of('holiday'),
            'nights' => $nightOnly + $fullDayNight + $halfDayNight,
            'worked_days' => number_format($workedDays, 1, '.', ''),
        ];
    }

    /**
     * The money side, each kind on its own line. Earned is every credit in the
     * range (the same rule the payroll report uses); the rest are the specific
     * debit types so advance and tiffin read apart from a plain payout.
     *
     * @return array{
     *     earned: string, night: string, advance: string, tiffin: string,
     *     payout: string, fine: string, deductions: string
     * }
     */
    public function moneyFor(Employee $employee, string $from, string $to): array
    {
        $row = EmployeeLedger::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('entry_date', [$from, $to])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0) AS earned,
                COALESCE(SUM(CASE WHEN type = 'night_allowance' THEN amount ELSE 0 END), 0) AS night,
                COALESCE(SUM(CASE WHEN type = 'advance' THEN amount ELSE 0 END), 0) AS advance,
                COALESCE(SUM(CASE WHEN type = 'tiffin' THEN amount ELSE 0 END), 0) AS tiffin,
                COALESCE(SUM(CASE WHEN type = 'payout' THEN amount ELSE 0 END), 0) AS payout,
                COALESCE(SUM(CASE WHEN type = 'fine' THEN amount ELSE 0 END), 0) AS fine,
                COALESCE(SUM(CASE WHEN type = 'deduction' THEN amount ELSE 0 END), 0) AS deduction
            ")
            ->first();

        return [
            'earned' => $this->money($row->earned),
            'night' => $this->money($row->night),
            'advance' => $this->money($row->advance),
            'tiffin' => $this->money($row->tiffin),
            'payout' => $this->money($row->payout),
            'fine' => $this->money($row->fine),
            // fine and custom deductions together are what the floor calls কর্তন.
            'deductions' => bcadd($this->money($row->fine), $this->money($row->deduction), 2),
        ];
    }

    private function money(int|string|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
