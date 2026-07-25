<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    $this->account = Account::factory()->create(['opening_balance' => 0, 'current_balance' => 0]);

    $this->order = Order::factory()->withTotals('50000.00')->create(['order_date' => '2026-07-20']);
    OrderItem::factory()->create(['order_id' => $this->order->id]);
});

it('confirms a draft and gives it a number', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/status", ['status' => 'confirmed'])
        ->assertRedirect();

    $order = $this->order->fresh();

    expect($order->status)->toBe(OrderStatus::Confirmed)
        ->and($order->order_no)->toBe('SH-2607-0001')
        ->and(OrderStatusLog::count())->toBe(1);
});

it('offers only the moves this order can make', function () {
    $this->actingAs($this->manager)
        ->get("/orders/{$this->order->id}")
        ->assertInertia(fn ($page) => $page
            ->where('nextStatuses.0.value', 'confirmed')
            ->where('nextStatuses.1.value', 'cancelled')
            ->has('nextStatuses', 2)
        );
});

it('offers nothing once an order is delivered', function () {
    $order = Order::factory()->confirmed()->create(['status' => OrderStatus::Delivered]);

    $this->actingAs($this->manager)
        ->get("/orders/{$order->id}")
        ->assertInertia(fn ($page) => $page->has('nextStatuses', 0));
});

/**
 * The transition rules are the action's. A refusal has to reach the user as a
 * readable message rather than a 500 or a silent no-op.
 */
it('reports a Bengali message when a move is not allowed', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/status", ['status' => 'delivered'])
        ->assertSessionHas('error');

    expect($this->order->fresh()->status)->toBe(OrderStatus::Draft)
        ->and(OrderStatusLog::count())->toBe(0)
        ->and(DB::table('number_series')->count())->toBe(0);
});

it('refuses a status that is not a status', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/status", ['status' => 'shipped'])
        ->assertSessionHasErrors('status');
});

it('walks an order all the way to delivered', function () {
    foreach (['confirmed', 'in_production', 'ready', 'delivered'] as $status) {
        $this->actingAs($this->manager)->post("/orders/{$this->order->id}/status", ['status' => $status]);
    }

    $order = $this->order->fresh();

    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->delivered_at)->not->toBeNull()
        ->and(OrderStatusLog::count())->toBe(4);
});

it('takes a payment and brings the due down', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '20000',
            'account_id' => $this->account->id,
            'paid_on' => '2026-07-20',
            'payment_method' => 'cash',
        ])
        ->assertRedirect();

    $order = $this->order->fresh();

    expect(Transaction::count())->toBe(1)
        ->and($order->paid_amount)->toBe('20000.00')
        ->and($order->due_amount)->toBe('30000.00')
        ->and($this->account->fresh()->current_balance)->toBe('20000.00');
});

it('lists the payments taken against the order', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/payments", [
        'amount' => '20000',
        'account_id' => $this->account->id,
        'paid_on' => '2026-07-20',
        'payment_method' => 'bkash',
    ]);

    $this->actingAs($this->manager)
        ->get("/orders/{$this->order->id}")
        ->assertInertia(fn ($page) => $page
            ->has('order.payments', 1)
            ->where('order.payments.0.amount', '20000.00')
            ->where('order.payments.0.payment_method', 'bkash')
        );
});

/**
 * Over the outstanding amount is a runtime refusal from the action, where the
 * order is locked and the due derived from the cash rows. It has to arrive as
 * a message with nothing written.
 */
it('refuses more than is owed and writes nothing', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '60000',
            'account_id' => $this->account->id,
            'paid_on' => '2026-07-20',
        ])
        ->assertSessionHas('error');

    expect(Transaction::count())->toBe(0)
        ->and($this->order->fresh()->due_amount)->toBe('50000.00')
        ->and($this->account->fresh()->current_balance)->toBe('0.00');
});

it('refuses a payment dated in the future', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '1000',
            'account_id' => $this->account->id,
            'paid_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('paid_on');

    expect(Transaction::count())->toBe(0);
});

it('refuses a payment with no account', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '1000',
            'paid_on' => '2026-07-20',
        ])
        ->assertSessionHasErrors('account_id');
});

it('refuses a zero payment', function () {
    $this->actingAs($this->manager)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '0',
            'account_id' => $this->account->id,
            'paid_on' => '2026-07-20',
        ])
        ->assertSessionHasErrors('amount');
});

it('stamps who took the money', function () {
    $this->actingAs($this->manager)->post("/orders/{$this->order->id}/payments", [
        'amount' => '1000',
        'account_id' => $this->account->id,
        'paid_on' => '2026-07-20',
    ]);

    expect(Transaction::sole()->created_by)->toBe($this->manager->id);
});

it('lets an accountant take money without being able to change the order', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '5000',
            'account_id' => $this->account->id,
            'paid_on' => '2026-07-20',
        ])
        ->assertRedirect();

    expect(Transaction::count())->toBe(1);

    $this->actingAs($accountant)
        ->post("/orders/{$this->order->id}/status", ['status' => 'confirmed'])
        ->assertForbidden();

    expect($this->order->fresh()->status)->toBe(OrderStatus::Draft);
});

it('keeps a storekeeper away from both', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->post("/orders/{$this->order->id}/status", ['status' => 'confirmed'])
        ->assertForbidden();

    $this->actingAs($storekeeper)
        ->post("/orders/{$this->order->id}/payments", [
            'amount' => '1000',
            'account_id' => $this->account->id,
            'paid_on' => '2026-07-20',
        ])
        ->assertForbidden();

    expect(Transaction::count())->toBe(0);
});

it('hides the payment control from someone who cannot take money', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->get("/orders/{$this->order->id}")
        ->assertInertia(fn ($page) => $page->where('canTakePayment', false)->where('canManage', false));
});
