<?php

namespace App\Queries;

use App\Enums\AccountType;
use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\EmployeeLedger;
use App\Models\Material;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * The figures the owner wants on one screen before opening anything else.
 *
 * Each block is a separate method because each is guarded by a different
 * permission: the controller asks only for what the reader is allowed to see,
 * so an accountant's dashboard never runs the order queries and a manager's
 * never runs the cash ones. Nothing here is cached; these are cheap aggregates
 * and a stale drawer figure is worse than a fast one.
 */
class DashboardSummary
{
    /**
     * What the drawers hold and what moved today.
     *
     * Cash accounts only, the same rule the daily closing uses: a bKash
     * balance is real money but it is not in the box.
     *
     * @return array{cash_in_hand: string, today_in: string, today_out: string}
     */
    public function cash(?string $onDate = null): array
    {
        $date = $onDate ?? CarbonImmutable::today()->toDateString();

        return [
            'cash_in_hand' => $this->money(
                Account::query()
                    ->where('type', AccountType::Cash)
                    ->where('is_active', true)
                    ->sum('current_balance')
            ),
            'today_in' => $this->movementOn($date, TransactionDirection::In),
            'today_out' => $this->movementOn($date, TransactionDirection::Out),
        ];
    }

    /**
     * What customers owe on jobs still open, and how the order book stands.
     *
     * @return array{receivable: string, open_orders: int, due_this_week: int, late_delivery: int}
     */
    public function orders(?string $onDate = null): array
    {
        $today = CarbonImmutable::parse($onDate ?? CarbonImmutable::today()->toDateString());

        return [
            'receivable' => $this->money(Order::query()->open()->sum('due_amount')),
            'open_orders' => Order::query()->open()->count(),
            'due_this_week' => Order::query()
                ->open()
                ->whereBetween('expected_delivery_date', [$today->toDateString(), $today->addDays(7)->toDateString()])
                ->count(),
            // Promised and not delivered. The one number a customer will ring
            // about, so it sits on the front page rather than in a report.
            'late_delivery' => Order::query()
                ->open()
                ->whereNotNull('expected_delivery_date')
                ->where('expected_delivery_date', '<', $today->toDateString())
                ->count(),
        ];
    }

    /**
     * What we owe suppliers, and how much of it is old.
     *
     * @return array{payable: string, owing_challans: int, overdue_challans: int}
     */
    public function payable(?string $onDate = null): array
    {
        $today = $onDate ?? CarbonImmutable::today()->toDateString();

        return [
            'payable' => $this->money(Purchase::query()->owing()->sum('due_amount')),
            'owing_challans' => Purchase::query()->owing()->count(),
            'overdue_challans' => Purchase::query()->overdue($today)->count(),
        ];
    }

    /**
     * What the shop owes its workers.
     *
     * Only the workers in credit are counted. A worker who has drawn more than
     * they have earned owes the shop, and netting that off against what
     * everyone else is owed would understate the wage bill waiting to be paid.
     *
     * @return array{worker_dues: string, workers_owed: int}
     */
    public function labour(): array
    {
        $balances = EmployeeLedger::query()
            ->groupBy('employee_id')
            ->selectRaw("employee_id, SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END) AS balance")
            ->havingRaw('balance > 0')
            ->pluck('balance');

        return [
            'worker_dues' => $this->money($balances->sum()),
            'workers_owed' => $balances->count(),
        ];
    }

    /**
     * @return array{low_stock: int}
     */
    public function stock(): array
    {
        return ['low_stock' => Material::query()->active()->lowStock()->count()];
    }

    private function movementOn(string $date, TransactionDirection $direction): string
    {
        return $this->money(
            Transaction::query()
                ->where('txn_date', $date)
                ->where('direction', $direction)
                ->sum('amount')
        );
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
