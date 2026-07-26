<?php

use App\Enums\MaterialCategory;
use App\Enums\MaterialMovementType;
use App\Enums\MaterialUnit;
use App\Enums\Role;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

/**
 * @return array<string, mixed>
 */
function materialPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'সেগুন কাঠ',
        'category' => MaterialCategory::Wood->value,
        'unit' => MaterialUnit::Cft->value,
        'min_stock' => '5',
        'opening_stock' => '0',
        'opening_cost' => '0',
        'is_active' => true,
    ], $overrides);
}

it('shows the list to the owner', function () {
    Material::factory()->count(3)->create();

    $this->actingAs($this->owner)->get('/materials')->assertOk();
});

it('creates exactly one material', function () {
    $this->actingAs($this->owner)
        ->post('/materials', materialPayload())
        ->assertRedirect('/materials');

    expect(Material::count())->toBe(1)
        ->and(Material::sole()->unit)->toBe(MaterialUnit::Cft)
        ->and(Material::sole()->current_stock)->toBe('0.000')
        ->and(MaterialMovement::count())->toBe(0);
});

/**
 * The rollout checklist calls for opening stock to be seeded on day one. It is
 * written as a movement, so even the opening figure has a row behind it.
 */
it('seeds day-one stock as a movement', function () {
    $this->actingAs($this->owner)
        ->post('/materials', materialPayload(['opening_stock' => '42.5', 'opening_cost' => '1200']))
        ->assertRedirect('/materials');

    $material = Material::sole();
    $movement = MaterialMovement::sole();

    expect($material->current_stock)->toBe('42.500')
        ->and($material->avg_cost)->toBe('1200.00')
        ->and($movement->type)->toBe(MaterialMovementType::In)
        ->and($movement->quantity)->toBe('42.500')
        ->and($movement->unit_cost)->toBe('1200.00')
        ->and($movement->material_id)->toBe($material->id);
});

it('rejects a blank name and writes nothing', function () {
    $this->actingAs($this->owner)
        ->post('/materials', materialPayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Material::count())->toBe(0);
});

it('rejects a unit it does not measure in', function () {
    $this->actingAs($this->owner)
        ->post('/materials', materialPayload(['unit' => 'truckload']))
        ->assertSessionHasErrors('unit');

    expect(Material::count())->toBe(0);
});

/**
 * Stock on hand is what material_movements adds up to. A figure typed over it
 * would make the movement log a story nobody can check.
 */
it('ignores stock sent on an edit', function () {
    $material = Material::factory()->inStock('10.000', '1000.00')->create();

    $this->actingAs($this->owner)
        ->put("/materials/{$material->id}", materialPayload([
            'name' => 'সেগুন কাঠ (ভালো)',
            'opening_stock' => '9999',
            'opening_cost' => '9999',
        ]));

    expect($material->refresh()->name)->toBe('সেগুন কাঠ (ভালো)')
        ->and($material->current_stock)->toBe('10.000')
        ->and($material->avg_cost)->toBe('1000.00')
        ->and(MaterialMovement::count())->toBe(0);
});

it('deletes a material that was never used', function () {
    $material = Material::factory()->create();

    $this->actingAs($this->owner)->delete("/materials/{$material->id}");

    expect(Material::count())->toBe(0);
});

/**
 * materials is the one master data table with no deleted_at column, so
 * anything with history behind it is refused rather than soft-deleted.
 */
it('refuses to delete a material with movements behind it', function () {
    $material = Material::factory()->create();
    MaterialMovement::factory()->create(['material_id' => $material->id]);

    $this->actingAs($this->owner)
        ->delete("/materials/{$material->id}")
        ->assertSessionHas('error');

    expect(Material::find($material->id))->not->toBeNull();
});

it('refuses to delete a material that is on a challan', function () {
    $material = Material::factory()->create();
    $purchase = Purchase::factory()->create();
    PurchaseItem::factory()->create(['purchase_id' => $purchase->id, 'item_id' => $material->id]);

    $this->actingAs($this->owner)
        ->delete("/materials/{$material->id}")
        ->assertSessionHas('error');

    expect(Material::find($material->id))->not->toBeNull();
});

it('refuses to delete a material still in stock', function () {
    $material = Material::factory()->inStock('4.000')->create();

    $this->actingAs($this->owner)
        ->delete("/materials/{$material->id}")
        ->assertSessionHas('error');

    expect(Material::find($material->id))->not->toBeNull();
});

it('counts what has fallen to the reorder line', function () {
    Material::factory()->inStock('4.000')->create(['min_stock' => '5.000']);
    Material::factory()->inStock('5.000')->create(['min_stock' => '5.000']);
    Material::factory()->inStock('50.000')->create(['min_stock' => '5.000']);
    // No reorder line set, so it never alerts however empty it is.
    Material::factory()->inStock('0.000')->create(['min_stock' => '0.000']);

    $this->actingAs($this->owner)
        ->get('/materials')
        ->assertInertia(fn ($page) => $page->where('lowCount', 2)->has('materials.data', 4));
});

it('filters the list down to what is running out', function () {
    Material::factory()->inStock('4.000')->create(['min_stock' => '5.000']);
    Material::factory()->inStock('50.000')->create(['min_stock' => '5.000']);

    $this->actingAs($this->owner)
        ->get('/materials?low=1')
        ->assertInertia(fn ($page) => $page->where('low', true)->has('materials.data', 1));
});

it('finds a material by name', function () {
    Material::factory()->create(['name' => 'সেগুন কাঠ']);
    Material::factory()->create(['name' => 'পিতলের কব্জা']);

    $this->actingAs($this->owner)
        ->get('/materials?search=কব্জা')
        ->assertInertia(fn ($page) => $page->has('materials.data', 1));
});

/**
 * The storekeeper is the one who counts the stock, so unlike most master data
 * they hold materials.manage.
 */
it('lets a storekeeper add a material', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/materials')->assertOk();
    $this->actingAs($storekeeper)->post('/materials', materialPayload())->assertRedirect('/materials');

    expect(Material::count())->toBe(1);
});

it('keeps an accountant out of the material list', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)->get('/materials')->assertForbidden();
});
