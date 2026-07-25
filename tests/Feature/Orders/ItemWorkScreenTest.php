<?php

use App\Enums\OrderItemWorkStatus;
use App\Enums\Role;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create();
    $this->manager->assignRole(Role::Manager->value);

    $this->order = Order::factory()->confirmed()->create();
    $this->item = OrderItem::factory()->create(['order_id' => $this->order->id]);
    $this->worker = Employee::factory()->piece()->create(['name' => 'সুমন']);
});

function workPayload(array $overrides = []): array
{
    return [
        'employee_id' => test()->worker->id,
        'work_type' => 'নকশা',
        'agreed_amount' => '3000',
        'assigned_date' => '2026-07-20',
        'status' => 'assigned',
        ...$overrides,
    ];
}

it('assigns work to a worker', function () {
    $this->actingAs($this->manager)
        ->post("/order-items/{$this->item->id}/works", workPayload())
        ->assertRedirect();

    expect(OrderItemWork::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('pays the worker when the job is marked done', function () {
    $this->actingAs($this->manager)->post("/order-items/{$this->item->id}/works", workPayload());
    $work = OrderItemWork::sole();

    $this->actingAs($this->manager)
        ->put("/order-items/{$this->item->id}/works/{$work->id}", workPayload(['status' => 'done']))
        ->assertRedirect();

    expect(EmployeeLedger::count())->toBe(1)
        ->and(app(LedgerService::class)->balanceFor($this->worker))->toBe('3000.00');
});

it('takes the money back when the job is reopened', function () {
    $this->actingAs($this->manager)->post("/order-items/{$this->item->id}/works", workPayload(['status' => 'done']));
    $work = OrderItemWork::sole();

    expect(app(LedgerService::class)->balanceFor($this->worker))->toBe('3000.00');

    $this->actingAs($this->manager)
        ->put("/order-items/{$this->item->id}/works/{$work->id}", workPayload(['status' => 'rejected']));

    expect(EmployeeLedger::count())->toBe(0)
        ->and(app(LedgerService::class)->balanceFor($this->worker))->toBe('0.00');
});

/**
 * A daily worker is already paid for those hours; a contract amount on top
 * would pay them twice for one day.
 */
it('reports a Bengali message for a contract amount on a daily worker', function () {
    $daily = Employee::factory()->create(['wage_type' => WageType::Daily, 'name' => 'রফিক']);

    $this->actingAs($this->manager)
        ->post("/order-items/{$this->item->id}/works", workPayload(['employee_id' => $daily->id]))
        ->assertSessionHas('error');

    expect(OrderItemWork::count())->toBe(0)
        ->and(EmployeeLedger::count())->toBe(0);
});

it('shows the work on the order with the piece workers marked', function () {
    OrderItemWork::factory()->create([
        'order_item_id' => $this->item->id,
        'employee_id' => $this->worker->id,
        'agreed_amount' => '3000',
    ]);

    $daily = Employee::factory()->create(['wage_type' => WageType::Daily]);

    $response = $this->actingAs($this->manager)->get("/orders/{$this->order->id}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->has('order.items.0.works', 1)
        ->where('order.items.0.works.0.employee', 'সুমন')
        ->has('workers', 2)
        ->has('workStatuses', 4)
    );

    // Keyed by id rather than position: the list is sorted by name, and which
    // name sorts first is not what this test is about.
    $workers = collect($response->viewData('page')['props']['workers'])->keyBy('value');

    expect($workers[$this->worker->id]['isPieceWorker'])->toBeTrue()
        ->and($workers[$daily->id]['isPieceWorker'])->toBeFalse();
});

it('deletes work that was never finished', function () {
    $work = OrderItemWork::factory()->create([
        'order_item_id' => $this->item->id,
        'employee_id' => $this->worker->id,
    ]);

    $this->actingAs($this->manager)
        ->delete("/order-items/{$this->item->id}/works/{$work->id}")
        ->assertRedirect();

    expect(OrderItemWork::count())->toBe(0);
});

it('refuses to delete finished work and says why', function () {
    $this->actingAs($this->manager)->post("/order-items/{$this->item->id}/works", workPayload(['status' => 'done']));
    $work = OrderItemWork::sole();

    $this->actingAs($this->manager)
        ->delete("/order-items/{$this->item->id}/works/{$work->id}")
        ->assertSessionHas('error');

    expect(OrderItemWork::count())->toBe(1)
        ->and(app(LedgerService::class)->balanceFor($this->worker))->toBe('3000.00');
});

/**
 * The work id is scoped to the item in the URL, so a guessed id from another
 * order cannot be reached.
 */
it('will not touch work belonging to a different item', function () {
    $otherItem = OrderItem::factory()->create();
    $work = OrderItemWork::factory()->create([
        'order_item_id' => $otherItem->id,
        'employee_id' => $this->worker->id,
    ]);

    $this->actingAs($this->manager)
        ->put("/order-items/{$this->item->id}/works/{$work->id}", workPayload())
        ->assertNotFound();

    $this->actingAs($this->manager)
        ->delete("/order-items/{$this->item->id}/works/{$work->id}")
        ->assertNotFound();

    expect($work->fresh()->status)->toBe(OrderItemWorkStatus::Assigned);
});

it('refuses an unknown work status', function () {
    $this->actingAs($this->manager)
        ->post("/order-items/{$this->item->id}/works", workPayload(['status' => 'finished']))
        ->assertSessionHasErrors('status');
});

it('keeps an accountant out of handing work around', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->post("/order-items/{$this->item->id}/works", workPayload())
        ->assertForbidden();

    expect(OrderItemWork::count())->toBe(0);
});

/**
 * The whole point of piece work: it reaches the worker's balance beside the
 * other two earning paths.
 */
it('lands on the worker balance screen alongside everything else', function () {
    $this->actingAs($this->manager)->post("/order-items/{$this->item->id}/works", workPayload(['status' => 'done']));

    $this->actingAs($this->manager)
        ->get("/employee-ledger/{$this->worker->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('balance', '3000.00')
            ->where('entries.data.0.type', 'piece_earned')
        );
});
