<?php

use App\Actions\Orders\SaveOrder;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use App\Models\Shop;
use App\Models\User;
use App\Support\ReferencedRecordException;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->action = app(SaveOrder::class);
    $this->customer = Customer::factory()->create();
    $this->shop = Shop::factory()->create();
});

function orderData(array $overrides = []): array
{
    return [
        'customer_id' => test()->customer->id,
        'shop_id' => test()->shop->id,
        'order_date' => '2026-07-20',
        'expected_delivery_date' => '2026-08-15',
        ...$overrides,
    ];
}

function itemRow(array $overrides = []): array
{
    return [
        'item_name' => 'সেগুন খাট',
        'quantity' => '1',
        'unit_price' => '25000',
        ...$overrides,
    ];
}

it('creates an order as a draft with no number', function () {
    $order = $this->action->handle(orderData(), [itemRow()]);

    expect(Order::count())->toBe(1)
        ->and(OrderItem::count())->toBe(1)
        ->and($order->status)->toBe(OrderStatus::Draft)
        ->and($order->order_no)->toBeNull();
});

/**
 * line_total is computed, never taken from the caller. A client sending its own
 * figure must not be able to change what the shop charges.
 */
it('computes line totals and ignores any sent by the caller', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(['quantity' => '3', 'unit_price' => '2500.50', 'line_total' => '1']),
    ]);

    expect($order->items()->first()->line_total)->toBe('7501.50');
});

it('sums the subtotal from the items', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(['unit_price' => '25000']),
        itemRow(['item_name' => 'আলমারি', 'unit_price' => '18000']),
        itemRow(['item_name' => 'সোফা', 'quantity' => '2', 'unit_price' => '15000']),
    ]);

    expect($order->subtotal)->toBe('73000.00')
        ->and($order->total_amount)->toBe('73000.00')
        ->and($order->due_amount)->toBe('73000.00');
});

it('takes the discount off and adds the delivery charge', function () {
    $order = $this->action->handle(
        orderData(['discount' => '3000', 'delivery_charge' => '1500']),
        [itemRow(['unit_price' => '50000'])]
    );

    // 50000 - 3000 + 1500
    expect($order->subtotal)->toBe('50000.00')
        ->and($order->total_amount)->toBe('48500.00')
        ->and($order->due_amount)->toBe('48500.00');
});

it('holds to the paisa on awkward figures', function () {
    $order = $this->action->handle(
        orderData(['discount' => '0.01']),
        [itemRow(['quantity' => '3', 'unit_price' => '333.33'])]
    );

    expect($order->subtotal)->toBe('999.99')
        ->and($order->total_amount)->toBe('999.98');
});

it('leaves the due as the total minus whatever has been paid', function () {
    $order = Order::factory()->create(['paid_amount' => '10000.00']);

    $this->action->handle(
        orderData(['customer_id' => $order->customer_id, 'shop_id' => $order->shop_id]),
        [itemRow(['unit_price' => '25000'])],
        $order
    );

    expect($order->fresh()->due_amount)->toBe('15000.00');
});

it('stamps who created it', function () {
    $user = User::factory()->create();

    $order = $this->action->handle(orderData(), [itemRow()], userId: $user->id);

    expect($order->created_by)->toBe($user->id);
});

it('edits an existing order without adding a second one', function () {
    $order = $this->action->handle(orderData(), [itemRow()]);

    $this->action->handle(orderData(['note' => 'বদলানো হয়েছে']), [
        ['id' => $order->items()->first()->id, ...itemRow(['unit_price' => '30000'])],
    ], $order);

    expect(Order::count())->toBe(1)
        ->and(OrderItem::count())->toBe(1)
        ->and($order->fresh()->note)->toBe('বদলানো হয়েছে')
        ->and($order->fresh()->total_amount)->toBe('30000.00');
});

it('adds a new item to an existing order', function () {
    $order = $this->action->handle(orderData(), [itemRow()]);
    $existingId = $order->items()->first()->id;

    $this->action->handle(orderData(), [
        ['id' => $existingId, ...itemRow()],
        itemRow(['item_name' => 'নতুন আলমারি', 'unit_price' => '20000']),
    ], $order);

    expect($order->items()->count())->toBe(2)
        ->and($order->fresh()->subtotal)->toBe('45000.00');
});

it('drops an item that was removed from the form', function () {
    $order = $this->action->handle(orderData(), [
        itemRow(),
        itemRow(['item_name' => 'বাদ যাবে', 'unit_price' => '9000']),
    ]);

    $keep = $order->items()->where('item_name', 'সেগুন খাট')->first();

    $this->action->handle(orderData(), [['id' => $keep->id, ...itemRow()]], $order);

    expect($order->items()->count())->toBe(1)
        ->and($order->fresh()->subtotal)->toBe('25000.00');
});

/**
 * order_items cascades to order_item_works. Dropping an item that a worker has
 * already been given would delete their piece-work record, while the ledger
 * entry it paid for stays forever. That leaves money pointing at nothing.
 */
it('refuses to drop an item whose work is already handed out', function () {
    $order = $this->action->handle(orderData(), [itemRow(), itemRow(['item_name' => 'কাজ চলছে'])]);

    $busy = $order->items()->where('item_name', 'কাজ চলছে')->first();
    OrderItemWork::factory()->create(['order_item_id' => $busy->id]);

    $keep = $order->items()->where('item_name', 'সেগুন খাট')->first();

    expect(fn () => $this->action->handle(orderData(), [['id' => $keep->id, ...itemRow()]], $order))
        ->toThrow(ReferencedRecordException::class);

    expect($order->items()->count())->toBe(2)
        ->and(OrderItemWork::count())->toBe(1);
});

/**
 * Definition of done, clause 4. The first item inserts, the second is rejected
 * by the database, and the order itself must go with both.
 */
it('persists nothing when a row is rejected part way through', function () {
    expect(fn () => $this->action->handle(orderData(), [
        itemRow(),
        // item_name is VARCHAR(200); MySQL in strict mode refuses this.
        itemRow(['item_name' => str_repeat('ক', 300)]),
    ]))->toThrow(QueryException::class);

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0);
});

it('recalculates the subtotal down to zero when every item goes', function () {
    $order = $this->action->handle(orderData(), [itemRow()]);

    $this->action->handle(orderData(), [], $order);

    expect($order->items()->count())->toBe(0)
        ->and($order->fresh()->subtotal)->toBe('0.00')
        ->and($order->fresh()->total_amount)->toBe('0.00');
});
