<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Services\NumberSeries;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moves an order to a new status, logging who did it.
 *
 * The transition rules live on OrderStatus. Delivered and cancelled are the
 * ends of the line; an order that has gone out of the door or been called off
 * does not move again.
 *
 * Leaving draft is also where the order earns its number. That happens exactly
 * once: an order that already has one keeps it, so re-confirming after an edit
 * does not burn a second number and leave a hole in the printed sequence.
 */
class ChangeOrderStatus
{
    public function __construct(private readonly NumberSeries $numbers) {}

    public function handle(
        Order $order,
        OrderStatus $to,
        ?int $userId = null,
        ?string $note = null,
    ): Order {
        $from = $order->status;

        if ($from === $to) {
            // Saving the same status twice is a no-op, not an error. It writes
            // no log line, so the trail stays readable.
            return $order;
        }

        if (! $from->canMoveTo($to)) {
            throw new RuntimeException(
                "\"{$from->label()}\" থেকে \"{$to->label()}\" করা যাবে না।"
            );
        }

        if ($to === OrderStatus::Confirmed && $order->items()->doesntExist()) {
            throw new RuntimeException('অন্তত একটি আইটেম ছাড়া অর্ডার নিশ্চিত করা যাবে না।');
        }

        return DB::transaction(function () use ($order, $from, $to, $userId, $note) {
            if ($to->needsNumber() && $order->order_no === null) {
                $order->order_no = $this->numbers->issue(
                    NumberSeries::ORDER,
                    $order->order_date->toDateString()
                );
            }

            $order->status = $to;

            if ($to === OrderStatus::Delivered) {
                $order->delivered_at = now();
            }

            $order->save();

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $userId,
                'note' => $note,
                'created_at' => now(),
            ]);

            return $order;
        });
    }
}
