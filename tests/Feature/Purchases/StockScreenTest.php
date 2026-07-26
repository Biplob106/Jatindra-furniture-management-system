<?php

use App\Enums\MaterialMovementType;
use App\Enums\MaterialUnit;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->material = Material::factory()
        ->inStock('100.000', '1200.00')
        ->create(['name' => 'সেগুন কাঠ', 'unit' => MaterialUnit::Cft]);
});

/**
 * @return array<string, mixed>
 */
function issuePayload(array $overrides = []): array
{
    return array_merge([
        'material_id' => test()->material->id,
        'quantity' => '10',
        'movement_date' => '2026-07-20',
        'type' => MaterialMovementType::Out->value,
    ], $overrides);
}

it('shows the store room with what can be moved', function () {
    MaterialMovement::factory()->count(2)->create(['material_id' => $this->material->id]);

    $this->actingAs($this->owner)
        ->get('/stock')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('movements.data', 2)
            ->has('materials', 1)
            ->has('issueTypes', 3)
        );
});

it('offers only jobs still being worked on', function () {
    Order::factory()->confirmed()->create();
    Order::factory()->confirmed()->create(['status' => OrderStatus::Delivered]);
    // A draft has no number to print on the issue slip.
    Order::factory()->create();

    $this->actingAs($this->owner)
        ->get('/stock')
        ->assertInertia(fn ($page) => $page->has('orders', 1));
});

it('issues material to a job', function () {
    $order = Order::factory()->confirmed()->create();

    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload(['order_id' => $order->id, 'note' => 'আলমারির জন্য']))
        ->assertRedirect('/stock')
        ->assertSessionHas('success');

    $movement = MaterialMovement::sole();

    expect($movement->order_id)->toBe($order->id)
        ->and($movement->quantity)->toBe('10.000')
        ->and($movement->unit_cost)->toBe('1200.00')
        ->and($this->material->refresh()->current_stock)->toBe('90.000')
        // Section 9: no money moves when stock is issued.
        ->and(Transaction::count())->toBe(0);
});

it('issues to general use with no job', function () {
    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload())
        ->assertRedirect('/stock');

    expect(MaterialMovement::sole()->order_id)->toBeNull();
});

it('writes off wastage', function () {
    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload(['type' => MaterialMovementType::Wastage->value, 'quantity' => '2.5']))
        ->assertRedirect('/stock');

    expect(MaterialMovement::sole()->type)->toBe(MaterialMovementType::Wastage)
        ->and($this->material->refresh()->current_stock)->toBe('97.500');
});

/**
 * A recount goes through the count form, where the direction comes from what
 * was counted rather than from a type.
 */
it('refuses an adjustment sent to the issue form', function () {
    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload(['type' => MaterialMovementType::Adjustment->value]))
        ->assertSessionHasErrors('type');

    expect(MaterialMovement::count())->toBe(0);
});

it('refuses to issue more than is on the floor', function () {
    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload(['quantity' => '150']))
        ->assertSessionHas('error');

    expect(MaterialMovement::count())->toBe(0)
        ->and($this->material->refresh()->current_stock)->toBe('100.000');
});

it('refuses an issue of nothing', function () {
    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload(['quantity' => '0']))
        ->assertSessionHasErrors('quantity');

    expect(MaterialMovement::count())->toBe(0);
});

it('refuses an issue dated in the future', function () {
    $this->actingAs($this->owner)
        ->post('/stock/issue', issuePayload(['movement_date' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('movement_date');

    expect(MaterialMovement::count())->toBe(0);
});

it('corrects the books to what was counted', function () {
    $this->actingAs($this->owner)
        ->post('/stock/count', [
            'material_id' => $this->material->id,
            'counted_stock' => '94.500',
            'movement_date' => '2026-07-20',
            'note' => 'পানিতে নষ্ট',
        ])
        ->assertRedirect('/stock')
        ->assertSessionHas('success');

    expect(MaterialMovement::sole()->type)->toBe(MaterialMovementType::Adjustment)
        ->and(MaterialMovement::sole()->quantity)->toBe('5.500')
        ->and($this->material->refresh()->current_stock)->toBe('94.500');
});

/**
 * Saying nothing was needed is more useful than a success message about a row
 * that does not exist.
 */
it('says so when the count agrees with the books', function () {
    $this->actingAs($this->owner)
        ->post('/stock/count', [
            'material_id' => $this->material->id,
            'counted_stock' => '100',
            'movement_date' => '2026-07-20',
        ])
        ->assertSessionHas('success', 'গণনা খাতার সাথে মিলে গেছে, কোনো সংশোধন লাগেনি।');

    expect(MaterialMovement::count())->toBe(0);
});

it('refuses a count below nothing', function () {
    $this->actingAs($this->owner)
        ->post('/stock/count', [
            'material_id' => $this->material->id,
            'counted_stock' => '-5',
            'movement_date' => '2026-07-20',
        ])
        ->assertSessionHasErrors('counted_stock');

    expect(MaterialMovement::count())->toBe(0);
});

it('filters the log down to one kind of movement', function () {
    MaterialMovement::factory()->create(['material_id' => $this->material->id]);
    MaterialMovement::factory()->consumed('4.000')->create(['material_id' => $this->material->id]);

    $this->actingAs($this->owner)
        ->get('/stock?type=out')
        ->assertInertia(fn ($page) => $page->has('movements.data', 1)->where('type', 'out'));
});

it('filters the log down to one material', function () {
    $other = Material::factory()->create();

    MaterialMovement::factory()->create(['material_id' => $this->material->id]);
    MaterialMovement::factory()->create(['material_id' => $other->id]);

    $this->actingAs($this->owner)
        ->get("/stock?material_id={$this->material->id}")
        ->assertInertia(fn ($page) => $page->has('movements.data', 1));
});

/**
 * The storekeeper moves stock; the manager reads what was consumed.
 */
it('lets a storekeeper move stock', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/stock')->assertOk();
    $this->actingAs($storekeeper)->post('/stock/issue', issuePayload())->assertRedirect('/stock');

    expect(MaterialMovement::count())->toBe(1);
});

it('keeps a manager to reading the log', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)->get('/stock')->assertOk();
    $this->actingAs($manager)->post('/stock/issue', issuePayload())->assertForbidden();
    $this->actingAs($manager)->post('/stock/count', [
        'material_id' => $this->material->id,
        'counted_stock' => '50',
        'movement_date' => '2026-07-20',
    ])->assertForbidden();

    expect(MaterialMovement::count())->toBe(0);
});

it('keeps an accountant out of the store room', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)->get('/stock')->assertForbidden();
});
