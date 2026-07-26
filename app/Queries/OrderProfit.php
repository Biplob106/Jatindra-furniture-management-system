<?php

namespace App\Queries;

use App\Enums\MaterialMovementType;
use App\Enums\OrderItemWorkStatus;
use App\Models\MaterialMovement;
use App\Models\Order;
use App\Models\OrderItemWork;
use Illuminate\Support\Facades\DB;

/**
 * What one order earned, against what it cost to make.
 *
 * docs/schema.md section 10 defines it as:
 *
 *   profit = total_amount
 *          - material cost from material_movements WHERE order_id = X
 *          - labour cost from order_item_works agreed_amount + allocated daily wages
 *          - allocated overhead
 *
 * Two of those terms are computed here and two are not, deliberately.
 *
 * Material and piece labour are answerable to the paisa: a movement carries the
 * average cost the stock was bought at, and piece work carries the amount that
 * was actually agreed with the worker.
 *
 * Daily wages and overhead are not. Attributing a day of a carpenter's time
 * across the four jobs he touched, or a month's rent across everything that
 * left the shop, needs an allocation rule nobody has written down. Guessing one
 * would produce a number that looks exact and is not, and a wrong profit figure
 * is worse than an incomplete one. They are reported as unattributed, so the
 * screen can say the margin is before those rather than pretend otherwise.
 */
class OrderProfit
{
    /**
     * @return array{
     *     revenue: string,
     *     material_cost: string,
     *     piece_labour_cost: string,
     *     direct_cost: string,
     *     gross_profit: string,
     *     margin_percent: string,
     *     has_unattributed_costs: true
     * }
     */
    public function forOrder(Order $order): array
    {
        $material = $this->materialCostFor($order);
        $labour = $this->pieceLabourFor($order);

        $revenue = (string) $order->total_amount;
        $directCost = bcadd($material, $labour, 2);
        $profit = bcsub($revenue, $directCost, 2);

        return [
            'revenue' => $revenue,
            'material_cost' => $material,
            'piece_labour_cost' => $labour,
            'direct_cost' => $directCost,
            'gross_profit' => $profit,
            'margin_percent' => $this->marginPercent($profit, $revenue),

            // Daily wages and overhead are never in the figures above. The
            // screen names what is missing rather than letting a reader assume
            // this is the whole cost.
            'has_unattributed_costs' => true,
        ];
    }

    /**
     * What the stock consumed by this job was worth.
     *
     * Costed at the movement's own unit_cost, which is the average at the
     * moment it left the store. Material returned to the shelf comes back off
     * the cost; an offcut written off as wastage does not, because the job
     * still ate the timber it was cut from.
     */
    private function materialCostFor(Order $order): string
    {
        $total = MaterialMovement::query()
            ->where('order_id', $order->id)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN -(quantity * unit_cost) ELSE quantity * unit_cost END), 0) AS cost',
                [MaterialMovementType::Return->value]
            )
            ->value('cost');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * What the piece workers on this job were promised.
     *
     * Only work that reached `done` counts. An agreed amount on a job still in
     * progress is a plan, not a cost, and it is the `done` transition that
     * writes the worker's ledger credit.
     */
    private function pieceLabourFor(Order $order): string
    {
        $total = OrderItemWork::query()
            ->join('order_items', 'order_items.id', '=', 'order_item_works.order_item_id')
            ->where('order_items.order_id', $order->id)
            ->where('order_item_works.status', OrderItemWorkStatus::Done)
            ->sum(DB::raw('order_item_works.agreed_amount'));

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Margin against revenue, to two places. An order with no revenue has no
     * margin rather than a division by zero.
     */
    private function marginPercent(string $profit, string $revenue): string
    {
        if (bccomp($revenue, '0.00', 2) === 0) {
            return '0.00';
        }

        return bcmul(bcdiv($profit, $revenue, 6), '100', 2);
    }
}
