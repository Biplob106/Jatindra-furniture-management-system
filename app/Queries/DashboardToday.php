<?php

namespace App\Queries;

use App\Models\Attendance;
use App\Models\DailyClosing;
use App\Models\Employee;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What still needs doing today, for the checklist at the top of the dashboard.
 *
 * Kept apart from DashboardSummary because these answer "is it done?" rather
 * than "how much?". Each block is its own method so the controller can skip
 * the ones the reader has no permission for.
 */
class DashboardToday
{
    /**
     * Workers marked today out of the workers who should be.
     *
     * Both sides count only active, not-deleted employees, so a worker marked
     * this morning and turned off this afternoon cannot make marked exceed total.
     *
     * @return array{marked: int, total: int}
     */
    public function attendance(?string $onDate = null): array
    {
        $date = $this->date($onDate);

        return [
            'marked' => Attendance::query()
                ->where('work_date', $date)
                ->whereHas('employee', fn ($query) => $query->where('is_active', true))
                ->count(),
            'total' => Employee::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * Whether each shop in the reader's scope has closed the day.
     *
     * A reader tied to a shop sees that shop even if it has since been
     * switched off; their own closing must not vanish from the list. A reader
     * with no shop sees every active shop.
     *
     * @return list<array{shop_id: int, name: string, closed: bool}>
     */
    public function closing(User $user, ?string $onDate = null): array
    {
        $date = $this->date($onDate);

        $shops = $user->shop_id
            ? Shop::withTrashed()->whereKey($user->shop_id)->get(['id', 'name'])
            : Shop::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $closed = DailyClosing::query()
            ->whereIn('shop_id', $shops->pluck('id'))
            ->where('closing_date', $date)
            ->whereNotNull('closed_at')
            ->pluck('shop_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $shops
            ->map(fn (Shop $shop) => [
                'shop_id' => $shop->id,
                'name' => $shop->name,
                'closed' => in_array($shop->id, $closed, true),
            ])
            ->values()
            ->all();
    }

    /**
     * The open orders furthest past their promised date, at most five.
     *
     * "Open" is the same scope the late-delivery tile counts, so the list and
     * the tile never disagree about which orders are late.
     *
     * @return list<array{id: int, order_no: ?string, customer_name: ?string, days_late: int}>
     */
    public function lateOrders(?string $onDate = null): array
    {
        $date = $this->date($onDate);

        return Order::query()
            ->open()
            ->whereNotNull('expected_delivery_date')
            ->where('expected_delivery_date', '<', $date)
            ->with('customer:id,name')
            ->select(['id', 'order_no', 'customer_id'])
            ->selectRaw('DATEDIFF(?, expected_delivery_date) AS days_late', [$date])
            ->orderBy('expected_delivery_date')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'customer_name' => $order->customer?->name,
                'days_late' => (int) $order->days_late,
            ])
            ->all();
    }

    private function date(?string $onDate): string
    {
        return $onDate ?? CarbonImmutable::today()->toDateString();
    }
}
