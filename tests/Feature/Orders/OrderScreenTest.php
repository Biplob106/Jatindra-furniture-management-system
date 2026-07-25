<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    $this->customer = Customer::factory()->create(['name' => 'রহিম মিয়া', 'phone' => '01712345678']);
    $this->shop = Shop::factory()->create();
});

function orderPayload(array $overrides = [], array $items = []): array
{
    return [
        'customer_id' => test()->customer->id,
        'shop_id' => test()->shop->id,
        'order_date' => '2026-07-20',
        'expected_delivery_date' => '2026-08-15',
        'items' => $items === [] ? [[
            'item_name' => 'সেগুন খাট',
            'quantity' => '1',
            'unit_price' => '45000',
        ]] : $items,
        ...$overrides,
    ];
}

it('shows the order book', function () {
    Order::factory()->confirmed()->create(['customer_id' => $this->customer->id]);

    $this->actingAs($this->manager)
        ->get('/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

/**
 * The counter finds an order by the number the customer reads off their slip,
 * or by their phone. Both have to work.
 */
it('finds an order by customer phone', function () {
    Order::factory()->confirmed()->create(['customer_id' => $this->customer->id]);
    Order::factory()->confirmed()->create();

    $this->actingAs($this->manager)
        ->get('/orders?search=01712345678')
        ->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.customer.name', 'রহিম মিয়া'));
});

it('finds an order by its number', function () {
    $order = Order::factory()->create([
        'order_no' => 'SH-2607-0042',
        'status' => OrderStatus::Confirmed,
        'customer_id' => $this->customer->id,
    ]);
    Order::factory()->confirmed()->create();

    $this->actingAs($this->manager)
        ->get('/orders?search=SH-2607-0042')
        ->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.id', $order->id));
});

it('filters to open orders only', function () {
    Order::factory()->confirmed()->create(['status' => OrderStatus::InProduction]);
    Order::factory()->confirmed()->create(['status' => OrderStatus::Delivered]);
    Order::factory()->confirmed()->create(['status' => OrderStatus::Cancelled]);

    $this->actingAs($this->manager)
        ->get('/orders?status=open')
        ->assertInertia(fn ($page) => $page->has('orders.data', 1));
});

it('filters by one status', function () {
    Order::factory()->confirmed()->create(['status' => OrderStatus::Ready]);
    Order::factory()->confirmed()->create(['status' => OrderStatus::InProduction]);

    $this->actingAs($this->manager)
        ->get('/orders?status=ready')
        ->assertInertia(fn ($page) => $page->has('orders.data', 1)->where('orders.data.0.status', 'ready'));
});

it('creates a draft order with its items', function () {
    $this->actingAs($this->manager)
        ->post('/orders', orderPayload())
        ->assertRedirect();

    $order = Order::sole();

    expect($order->status)->toBe(OrderStatus::Draft)
        ->and($order->order_no)->toBeNull()
        ->and($order->items)->toHaveCount(1)
        ->and($order->total_amount)->toBe('45000.00')
        ->and($order->created_by)->toBe($this->manager->id);
});

it('creates an order with several items and totals them', function () {
    $this->actingAs($this->manager)->post('/orders', orderPayload(
        ['discount' => '2000', 'delivery_charge' => '500'],
        [
            ['item_name' => 'খাট', 'quantity' => '1', 'unit_price' => '45000'],
            ['item_name' => 'আলমারি', 'quantity' => '2', 'unit_price' => '18000'],
        ]
    ));

    $order = Order::sole();

    // 45000 + 36000 - 2000 + 500
    expect($order->subtotal)->toBe('81000.00')
        ->and($order->total_amount)->toBe('79500.00')
        ->and($order->due_amount)->toBe('79500.00');
});

/**
 * The client cannot decide what the shop charges.
 */
it('ignores totals sent by the client', function () {
    $this->actingAs($this->manager)->post('/orders', orderPayload([
        'subtotal' => '1',
        'total_amount' => '1',
        'due_amount' => '1',
        'paid_amount' => '9999',
    ]));

    $order = Order::sole();

    expect($order->total_amount)->toBe('45000.00')
        ->and($order->paid_amount)->toBe('0.00');
});

it('refuses an order with no items', function () {
    $this->actingAs($this->manager)
        ->post('/orders', orderPayload(['items' => []]))
        ->assertSessionHasErrors('items');

    expect(Order::count())->toBe(0);
});

it('refuses an item with no name', function () {
    $this->actingAs($this->manager)
        ->post('/orders', orderPayload([], [['quantity' => '1', 'unit_price' => '100']]))
        ->assertSessionHasErrors('items.0.item_name');

    expect(Order::count())->toBe(0);
});

it('refuses a zero quantity', function () {
    $this->actingAs($this->manager)
        ->post('/orders', orderPayload([], [['item_name' => 'খাট', 'quantity' => '0', 'unit_price' => '100']]))
        ->assertSessionHasErrors('items.0.quantity');
});

it('refuses a delivery date before the order date', function () {
    $this->actingAs($this->manager)
        ->post('/orders', orderPayload(['expected_delivery_date' => '2026-07-19']))
        ->assertSessionHasErrors('expected_delivery_date');

    expect(Order::count())->toBe(0);
});

it('shows one order with its items and money', function () {
    $order = Order::factory()->confirmed()->withTotals('50000.00', '20000.00')->create([
        'customer_id' => $this->customer->id,
    ]);
    OrderItem::factory()->create(['order_id' => $order->id, 'item_name' => 'সেগুন খাট']);

    $this->actingAs($this->manager)
        ->get("/orders/{$order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('order.paid_amount', '20000.00')
            ->where('order.due_amount', '30000.00')
            ->where('order.customer.name', 'রহিম মিয়া')
            ->has('order.items', 1)
            ->where('order.items.0.item_name', 'সেগুন খাট')
        );
});

it('renders the dimensions as one readable line', function () {
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'length' => 72, 'width' => 60, 'height' => 24,
    ]);

    $this->actingAs($this->manager)
        ->get("/orders/{$order->id}")
        ->assertInertia(fn ($page) => $page->where('order.items.0.dimensions', '72 × 60 × 24 ইঞ্চি'));
});

it('leaves the dimensions empty when the piece was not measured', function () {
    $order = Order::factory()->confirmed()->create();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'length' => null, 'width' => null, 'height' => null,
    ]);

    $this->actingAs($this->manager)
        ->get("/orders/{$order->id}")
        ->assertInertia(fn ($page) => $page->where('order.items.0.dimensions', null));
});

it('edits an open order', function () {
    $this->actingAs($this->manager)->post('/orders', orderPayload());
    $order = Order::sole();
    $itemId = $order->items()->first()->id;

    $this->actingAs($this->manager)
        ->put("/orders/{$order->id}", orderPayload(['note' => 'বদলানো'], [
            ['id' => $itemId, 'item_name' => 'সেগুন খাট', 'quantity' => '1', 'unit_price' => '50000'],
        ]))
        ->assertRedirect();

    expect($order->fresh()->total_amount)->toBe('50000.00')
        ->and($order->fresh()->note)->toBe('বদলানো')
        ->and(Order::count())->toBe(1);
});

/**
 * A delivered or cancelled order is history.
 */
it('refuses to edit a finished order', function (string $status) {
    $order = Order::factory()->confirmed()->create(['status' => OrderStatus::from($status)]);
    OrderItem::factory()->create(['order_id' => $order->id]);

    $this->actingAs($this->manager)
        ->get("/orders/{$order->id}/edit")
        ->assertRedirect(route('orders.show', $order));

    $this->actingAs($this->manager)
        ->put("/orders/{$order->id}", orderPayload())
        ->assertSessionHas('error');

    expect($order->fresh()->total_amount)->toBe('0.00');
})->with(['delivered', 'cancelled']);

it('filters the customer list by phone on the create screen', function () {
    Customer::factory()->create(['name' => 'অন্য কেউ', 'phone' => '01999999999']);

    $this->actingAs($this->manager)
        ->get('/orders/create?customer_search=01712345678')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('customers', 1)->where('customers.0.name', 'রহিম মিয়া'));
});

it('does not send every customer to the browser', function () {
    Customer::factory()->count(30)->create();

    $this->actingAs($this->manager)
        ->get('/orders/create')
        ->assertInertia(fn ($page) => $page->has('customers', 20));
});

it('keeps orders/create from being read as an order id', function () {
    $this->actingAs($this->manager)
        ->get('/orders/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('orders/create'));
});

it('lets an accountant read orders but not take one', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)->get('/orders')->assertOk();
    $this->actingAs($accountant)->get('/orders/create')->assertForbidden();
    $this->actingAs($accountant)->post('/orders', orderPayload())->assertForbidden();

    expect(Order::count())->toBe(0);
});

it('lets a storekeeper read orders, since they need to know what to build', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/orders')->assertOk();
    $this->actingAs($storekeeper)->get('/orders/create')->assertForbidden();
});
