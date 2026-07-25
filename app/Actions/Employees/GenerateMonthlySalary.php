<?php

namespace App\Actions\Employees;

use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Credits one month's salary to every monthly-paid worker.
 *
 * Monthly staff earn nothing from being marked present; their attendance is
 * recorded for the record only, and the money lands here instead, once, dated
 * the last day of the month.
 *
 * This runs more than once against the same month. Somebody runs it on the
 * 31st, a salary is corrected, it runs again. The second run must not pay
 * anyone twice, so an existing credit for that month is reconciled in place
 * rather than added to.
 *
 * Salaries are not prorated. docs/schema.md credits the full monthly_salary
 * regardless of attendance; a worker who missed days is handled with a `fine`
 * or an `adjustment`, which keeps the reason visible in the ledger instead of
 * buried in a arithmetic nobody can retrace.
 */
class GenerateMonthlySalary
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @param  string  $month  Any date inside the month, or YYYY-MM.
     * @return array{credited: int, skipped: int, total: string}
     */
    public function handle(string $month, ?int $createdBy = null): array
    {
        $entryDate = $this->lastDayOf($month);

        return DB::transaction(function () use ($entryDate, $createdBy) {
            $employees = Employee::query()
                ->where('is_active', true)
                ->where('wage_type', WageType::Monthly)
                ->get();

            $credited = 0;
            $skipped = 0;
            $total = '0.00';

            foreach ($employees as $employee) {
                $amount = (string) $employee->monthly_salary;

                if (bccomp($amount, '0.00', 2) <= 0) {
                    // No salary on file. Crediting zero would just be noise.
                    $skipped++;

                    continue;
                }

                $existing = $this->existingCreditFor($employee, $entryDate);

                if ($existing !== null) {
                    // Already paid for this month. Correct the figure if the
                    // salary changed, but never add a second row.
                    if (bccomp((string) $existing->amount, $amount, 2) !== 0) {
                        $existing->update(['amount' => $amount]);
                    }

                    $skipped++;

                    continue;
                }

                $this->ledger->record(
                    employee: $employee,
                    type: LedgerEntryType::WageEarned,
                    amount: $amount,
                    entryDate: $entryDate,
                    note: 'মাসিক বেতন',
                    createdBy: $createdBy,
                );

                $credited++;
                $total = bcadd($total, $amount, 2);
            }

            return ['credited' => $credited, 'skipped' => $skipped, 'total' => $total];
        });
    }

    /**
     * The month's salary row, if one is already there.
     *
     * Monthly workers never receive attendance-driven wage rows, so a
     * wage_earned credit on this date can only be this action's own work. The
     * null reference check keeps that true even if that ever changes.
     */
    private function existingCreditFor(Employee $employee, string $entryDate): ?EmployeeLedger
    {
        return EmployeeLedger::query()
            ->where('employee_id', $employee->id)
            ->where('type', LedgerEntryType::WageEarned)
            ->where('entry_date', $entryDate)
            ->whereNull('reference_type')
            ->first();
    }

    private function lastDayOf(string $month): string
    {
        $parsed = preg_match('/^\d{4}-\d{2}$/', $month)
            ? CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')
            : CarbonImmutable::parse($month);

        return $parsed->endOfMonth()->toDateString();
    }
}
