<?php

namespace App\Queries;

use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Services\LedgerService;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The one authoritative payroll calculation, reused by the report and the
 * dashboard so no two screens compute a worker's pay a different way.
 *
 * For a date range it reports, per worker:
 *   earned      — everything credited in the range (wage, night, overtime,
 *                 bonus, piece); a credit is a credit.
 *   night       — the night-allowance slice of that, shown on its own.
 *   paid        — cash actually handed over (payout, advance, tiffin).
 *   deductions  — debits that move no cash (fine, deduction).
 *   balance     — the all-time SUM(credit) - SUM(debit); what the shop still
 *                 owes the worker now. Positive means owed; negative means the
 *                 worker has taken more than earned and owes it back.
 *
 * The balance is deliberately all-time, not the range: a partial payment made
 * next month must not make this month look unpaid, and money owed from before
 * the window is still owed. Earnings and payments are the period's; the balance
 * is the standing position.
 */
class PayrollSummary
{
    /** Types that hand real money to the worker. */
    private const PAID = ['payout', 'advance', 'tiffin'];

    /** Debits that move no cash — they only lower what the shop owes. */
    private const DEDUCTIONS = ['fine', 'deduction'];

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * @return list<array{
     *     id: int, name: string, employee_code: string, wage_type: string,
     *     earned: string, night: string, paid: string, deductions: string, balance: string
     * }>
     */
    public function rows(string $from, string $to, ?int $shopId = null): array
    {
        $this->assertDates($from, $to);

        $employees = Employee::query()
            ->where(fn ($query) => $query
                ->where('is_active', true)
                ->orWhereIn('id', $this->employeeIdsWithEntries($from, $to)))
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId))
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'wage_type']);

        $ids = $employees->pluck('id')->all();
        $range = $this->rangeAggregates($from, $to, $ids);
        $balances = $this->ledger->balancesFor($ids);

        return $employees->map(function (Employee $employee) use ($range, $balances) {
            $figures = $range->get($employee->id);

            return [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_code,
                'wage_type' => $employee->wage_type->value,
                'earned' => $this->money($figures->earned ?? 0),
                'night' => $this->money($figures->night ?? 0),
                'paid' => $this->money($figures->paid ?? 0),
                'deductions' => $this->money($figures->deductions ?? 0),
                'balance' => $balances[$employee->id] ?? '0.00',
            ];
        })->all();
    }

    /**
     * Column totals across the whole crew, each summed to the paisa.
     *
     * @return array{earned: string, night: string, paid: string, deductions: string, outstanding: string}
     */
    public function totals(string $from, string $to, ?int $shopId = null): array
    {
        $rows = $this->rows($from, $to, $shopId);

        $sum = fn (string $key) => array_reduce(
            $rows,
            fn (string $carry, array $row) => bcadd($carry, $row[$key], 2),
            '0.00'
        );

        // Outstanding counts only what is owed, not netted against workers who
        // owe the shop, so it matches the "worker dues" figure elsewhere.
        $outstanding = array_reduce(
            $rows,
            fn (string $carry, array $row) => bccomp($row['balance'], '0.00', 2) > 0 ? bcadd($carry, $row['balance'], 2) : $carry,
            '0.00'
        );

        return [
            'earned' => $sum('earned'),
            'night' => $sum('night'),
            'paid' => $sum('paid'),
            'deductions' => $sum('deductions'),
            'outstanding' => $outstanding,
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function rangeAggregates(string $from, string $to, array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $paid = $this->sqlList(self::PAID);
        $deductions = $this->sqlList(self::DEDUCTIONS);

        return EmployeeLedger::query()
            ->whereIn('employee_id', $ids)
            ->whereBetween('entry_date', [$from, $to])
            ->groupBy('employee_id')
            ->selectRaw("employee_id,
                COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0) AS earned,
                COALESCE(SUM(CASE WHEN type = 'night_allowance' THEN amount ELSE 0 END), 0) AS night,
                COALESCE(SUM(CASE WHEN type IN ({$paid}) THEN amount ELSE 0 END), 0) AS paid,
                COALESCE(SUM(CASE WHEN type IN ({$deductions}) THEN amount ELSE 0 END), 0) AS deductions")
            ->get()
            ->keyBy('employee_id');
    }

    /**
     * @return list<int>
     */
    private function employeeIdsWithEntries(string $from, string $to): array
    {
        return EmployeeLedger::query()
            ->whereBetween('entry_date', [$from, $to])
            ->distinct()
            ->pluck('employee_id')
            ->all();
    }

    /**
     * A quoted, comma-joined list for an IN clause. The values are fixed
     * constants in this class, never user input.
     *
     * @param  list<string>  $values
     */
    private function sqlList(array $values): string
    {
        return implode(',', array_map(fn (string $value) => "'{$value}'", $values));
    }

    private function money(int|string|float $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function assertDates(string $from, string $to): void
    {
        foreach ([$from, $to] as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidArgumentException("Expected a Y-m-d date, got {$date}.");
            }
        }

        if ($from > $to) {
            throw new InvalidArgumentException('The range starts after it ends.');
        }
    }
}
