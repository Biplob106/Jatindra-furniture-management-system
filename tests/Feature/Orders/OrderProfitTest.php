<?php

use App\Actions\Materials\IssueMaterial;
use App\Enums\MaterialMovementType;
use App\Enums\OrderItemWorkStatus;
use App\Enums\Role;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use App\Models\User;
use App\Queries\OrderProfit;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->profit = app(OrderProfit::class);
    $this->issue = app(IssueMaterial::class);

    $this->order = Order::factory()->confirmed()->withTotals('50000.00')->create();
    $this->item = OrderItem::factory()->create(['order_id' => $this->order->id]);

    $this->timber = Material::factory()->inStock('100.000', '1200.00')->create();
});

it('reads revenue off the order', function () {
    expect($this->profit->forOrder($this->order)['revenue'])->toBe('50000.00');
});

/**
 * Costed at the movement's own unit_cost, which is the average at the moment
 * the stock left the store.
 */
it('counts what the job took out of the store', function () {
    $this->issue->handle($this->timber, '10', '2026-07-20', order: $this->order);

    $figures = $this->profit->forOrder($this->order);

    expect($figures['material_cost'])->toBe('12000.00')
        ->and($figures['gross_profit'])->toBe('38000.00');
});

it('leaves another job material out of it', function () {
    $other = Order::factory()->confirmed()->create();

    $this->issue->handle($this->timber, '10', '2026-07-20', order: $this->order);
    $this->issue->handle($this->timber, '5', '2026-07-20', order: $other);

    expect($this->profit->forOrder($this->order)['material_cost'])->toBe('12000.00');
});

it('leaves general store use out of every job', function () {
    $this->issue->handle($this->timber, '10', '2026-07-20');

    expect($this->profit->forOrder($this->order)['material_cost'])->toBe('0.00');
});

/**
 * The job still ate the timber the offcut was cut from, so wastage stays on
 * its cost. Material carried back to the shelf does not.
 */
it('keeps wastage on the job but takes returns back off', function () {
    $this->issue->handle($this->timber, '10', '2026-07-20', order: $this->order);
    $this->issue->handle($this->timber, '1', '2026-07-21', MaterialMovementType::Wastage, order: $this->order);
    $this->issue->handle($this->timber, '2', '2026-07-22', MaterialMovementType::Return, order: $this->order);

    // (10 + 1 - 2) * 1200
    expect($this->profit->forOrder($this->order)['material_cost'])->toBe('10800.00');
});

/**
 * An agreed amount on work still in progress is a plan, not a cost. It is the
 * `done` transition that writes the worker's ledger credit.
 */
it('counts only piece work that was finished', function () {
    OrderItemWork::factory()->create([
        'order_item_id' => $this->item->id,
        'agreed_amount' => '3000.00',
        'status' => OrderItemWorkStatus::Done,
    ]);
    OrderItemWork::factory()->create([
        'order_item_id' => $this->item->id,
        'agreed_amount' => '5000.00',
        'status' => OrderItemWorkStatus::Working,
    ]);

    expect($this->profit->forOrder($this->order)['piece_labour_cost'])->toBe('3000.00');
});

it('adds material and labour into one direct cost', function () {
    $this->issue->handle($this->timber, '10', '2026-07-20', order: $this->order);

    OrderItemWork::factory()->create([
        'order_item_id' => $this->item->id,
        'agreed_amount' => '3000.00',
        'status' => OrderItemWorkStatus::Done,
    ]);

    $figures = $this->profit->forOrder($this->order);

    expect($figures['direct_cost'])->toBe('15000.00')
        ->and($figures['gross_profit'])->toBe('35000.00')
        ->and($figures['margin_percent'])->toBe('70.00');
});

it('reports a job that cost more than it earned as a loss', function () {
    $cheap = Order::factory()->confirmed()->withTotals('5000.00')->create();

    $this->issue->handle($this->timber, '10', '2026-07-20', order: $cheap);

    $figures = $this->profit->forOrder($cheap);

    expect($figures['gross_profit'])->toBe('-7000.00')
        ->and($figures['margin_percent'])->toBe('-140.00');
});

it('has no margin rather than a division by zero', function () {
    $free = Order::factory()->confirmed()->create();

    expect($this->profit->forOrder($free)['margin_percent'])->toBe('0.00');
});

it('holds to the paisa', function () {
    $odd = Material::factory()->inStock('100.000', '1234.57')->create();
    $order = Order::factory()->confirmed()->withTotals('10000.00')->create();

    $this->issue->handle($odd, '1.500', '2026-07-20', order: $order);

    // 1.5 * 1234.57 = 1851.855, summed in SQL and rounded to the paisa
    expect($this->profit->forOrder($order)['material_cost'])->toBe('1851.86');
});

/**
 * Daily wages and overhead need an allocation rule nobody has written down.
 * The flag is how the screen knows to say the margin is before those.
 */
it('says plainly that some costs are not attributed', function () {
    expect($this->profit->forOrder($this->order)['has_unattributed_costs'])->toBeTrue();
});

it('shows the owner the profit on the order screen', function () {
    $this->issue->handle($this->timber, '10', '2026-07-20', order: $this->order);

    $this->actingAs($this->owner)
        ->get("/orders/{$this->order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('profit.material_cost', '12000.00')
            ->where('profit.gross_profit', '38000.00')
            ->has('materialUsed', 1)
            ->where('materialUsed.0.line_cost', '12000.00')
        );
});

/**
 * Enforced server-side, not by hiding a panel: the figures never reach anyone
 * else's browser.
 */
it('sends no profit figures to anyone but the owner', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->issue->handle($this->timber, '10', '2026-07-20', order: $this->order);

    $this->actingAs($user)
        ->get("/orders/{$this->order->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('profit', null)->has('materialUsed', 0));
})->with([Role::Manager->value, Role::Storekeeper->value, Role::Accountant->value]);
