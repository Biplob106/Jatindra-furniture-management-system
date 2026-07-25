<?php

use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->action = app(ChangeOrderStatus::class);
    $this->order = Order::factory()->create(['order_date' => '2026-07-20']);
    OrderItem::factory()->create(['order_id' => $this->order->id]);
});

it('issues the order number when a draft is confirmed', function () {
    expect($this->order->order_no)->toBeNull();

    $this->action->handle($this->order, OrderStatus::Confirmed);

    expect($this->order->fresh()->order_no)->toBe('SH-2607-0001')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Confirmed);
});

it('takes the number from the order month, not today', function () {
    Carbon::setTestNow('2026-09-05 10:00:00');

    $order = Order::factory()->create(['order_date' => '2026-07-20']);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->action->handle($order, OrderStatus::Confirmed);

    expect($order->fresh()->order_no)->toStartWith('SH-2607-');

    Carbon::setTestNow();
});

/**
 * A second number would leave a hole in a sequence that gets written on paper
 * slips, and a missing number reads as a lost order.
 */
it('never issues a second number once an order has one', function () {
    $this->action->handle($this->order, OrderStatus::Confirmed);
    $first = $this->order->fresh()->order_no;

    $this->action->handle($this->order, OrderStatus::InProduction);
    $this->action->handle($this->order, OrderStatus::Ready);

    expect($this->order->fresh()->order_no)->toBe($first)
        ->and(DB::table('number_series')->value('last_number'))->toBe(1);
});

it('logs every move with who made it', function () {
    $user = User::factory()->create();

    $this->action->handle($this->order, OrderStatus::Confirmed, $user->id, 'কাস্টমার অগ্রিম দিয়েছে');

    $log = OrderStatusLog::sole();

    expect($log->from_status)->toBe('draft')
        ->and($log->to_status)->toBe('confirmed')
        ->and($log->changed_by)->toBe($user->id)
        ->and($log->note)->toBe('কাস্টমার অগ্রিম দিয়েছে');
});

it('builds a trail across several moves', function () {
    $this->action->handle($this->order, OrderStatus::Confirmed);
    $this->action->handle($this->order, OrderStatus::InProduction);
    $this->action->handle($this->order, OrderStatus::Ready);

    expect(OrderStatusLog::count())->toBe(3)
        ->and(OrderStatusLog::orderBy('id')->pluck('to_status')->all())
        ->toBe(['confirmed', 'in_production', 'ready']);
});

it('treats setting the same status again as a no-op', function () {
    $this->action->handle($this->order, OrderStatus::Confirmed);
    $this->action->handle($this->order, OrderStatus::Confirmed);

    expect(OrderStatusLog::count())->toBe(1);
});

it('stamps delivered_at on delivery', function () {
    Carbon::setTestNow('2026-08-15 16:30:00');

    $this->action->handle($this->order, OrderStatus::Confirmed);
    $this->action->handle($this->order, OrderStatus::Ready);
    $this->action->handle($this->order, OrderStatus::Delivered);

    expect($this->order->fresh()->delivered_at->toDateTimeString())->toBe('2026-08-15 16:30:00');

    Carbon::setTestNow();
});

it('refuses a move the status does not allow', function (string $from, string $to) {
    $order = Order::factory()->create(['status' => OrderStatus::from($from)]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    expect(fn () => $this->action->handle($order, OrderStatus::from($to)))
        ->toThrow(RuntimeException::class);

    expect($order->fresh()->status->value)->toBe($from)
        ->and(OrderStatusLog::count())->toBe(0);
})->with([
    'draft straight to delivered' => ['draft', 'delivered'],
    'draft straight to ready' => ['draft', 'ready'],
    'confirmed back to draft' => ['confirmed', 'draft'],
    'ready back to confirmed' => ['ready', 'confirmed'],
]);

/**
 * Delivered and cancelled are the ends of the line. Correcting either is a
 * deliberate act, not a status change.
 */
it('will not move an order that is already finished', function (string $terminal) {
    $order = Order::factory()->confirmed()->create(['status' => OrderStatus::from($terminal)]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    foreach (OrderStatus::cases() as $target) {
        if ($target->value === $terminal) {
            continue;
        }

        expect(fn () => $this->action->handle($order, $target))->toThrow(RuntimeException::class);
    }

    expect($order->fresh()->status->value)->toBe($terminal);
})->with(['delivered', 'cancelled']);

it('lets a ready order go back into production', function () {
    $this->action->handle($this->order, OrderStatus::Confirmed);
    $this->action->handle($this->order, OrderStatus::Ready);
    $this->action->handle($this->order, OrderStatus::InProduction);

    expect($this->order->fresh()->status)->toBe(OrderStatus::InProduction);
});

it('refuses to confirm an order with no items', function () {
    $empty = Order::factory()->create();

    expect(fn () => $this->action->handle($empty, OrderStatus::Confirmed))
        ->toThrow(RuntimeException::class);

    expect($empty->fresh()->order_no)->toBeNull()
        ->and(DB::table('number_series')->count())->toBe(0);
});

/**
 * A cancelled draft has still left draft, so needsNumber() is true and it takes
 * a number. That is deliberate: a cancellation the shop may have to explain to
 * a customer is worth having a reference for.
 */
it('gives a cancelled draft a number, since it left draft', function () {
    $this->action->handle($this->order, OrderStatus::Cancelled);

    expect($this->order->fresh()->order_no)->toBe('SH-2607-0001');
});

it('numbers orders in sequence as they are confirmed', function () {
    $second = Order::factory()->create(['order_date' => '2026-07-21']);
    OrderItem::factory()->create(['order_id' => $second->id]);

    $this->action->handle($this->order, OrderStatus::Confirmed);
    $this->action->handle($second, OrderStatus::Confirmed);

    expect($this->order->fresh()->order_no)->toBe('SH-2607-0001')
        ->and($second->fresh()->order_no)->toBe('SH-2607-0002');
});

it('persists nothing when the move is refused', function () {
    $before = DB::table('number_series')->count();

    expect(fn () => $this->action->handle($this->order, OrderStatus::Delivered))
        ->toThrow(RuntimeException::class);

    expect(DB::table('number_series')->count())->toBe($before)
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Draft)
        ->and(OrderStatusLog::count())->toBe(0);
});
