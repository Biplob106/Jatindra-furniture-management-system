<?php

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockMovement;
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
function productPayload(array $overrides = []): array
{
    return array_merge([
        'sku' => 'ALM-001',
        'name' => 'সেগুন আলমারি',
        'category_id' => null,
        'wood_type' => 'সেগুন',
        'size_label' => '৬ × ৩ ফুট',
        'cost_price' => '18000',
        'sale_price' => '25000',
        'min_stock' => '2',
        'opening_stock' => '0',
        'shop_id' => null,
        'is_active' => true,
    ], $overrides);
}

it('shows the list to the owner', function () {
    Product::factory()->count(3)->create();

    $this->actingAs($this->owner)->get('/products')->assertOk();
});

it('creates exactly one product', function () {
    $this->actingAs($this->owner)
        ->post('/products', productPayload())
        ->assertRedirect('/products');

    expect(Product::count())->toBe(1)
        ->and(Product::sole()->sale_price)->toBe('25000.00')
        ->and(Product::sole()->current_stock)->toBe('0.00')
        ->and(StockMovement::count())->toBe(0);
});

/**
 * Nobody knows any more whether the pieces already on the floor were built or
 * bought, so day-one stock is recorded as a count rather than a guess at their
 * history.
 */
it('seeds day-one stock as an adjustment movement', function () {
    $this->actingAs($this->owner)
        ->post('/products', productPayload(['opening_stock' => '4']))
        ->assertRedirect('/products');

    $product = Product::sole();
    $movement = StockMovement::sole();

    expect($product->current_stock)->toBe('4.00')
        ->and($movement->type)->toBe(StockMovementType::Adjustment)
        ->and($movement->quantity)->toBe('4.00')
        ->and($movement->unit_cost)->toBe('18000.00')
        ->and($movement->created_by)->toBe($this->owner->id);
});

it('refuses a code another product already uses', function () {
    Product::factory()->create(['sku' => 'ALM-001']);

    $this->actingAs($this->owner)
        ->post('/products', productPayload())
        ->assertSessionHasErrors('sku');

    expect(Product::count())->toBe(1);
});

/**
 * A switched-off product keeps its code reserved, because the column carries a
 * plain UNIQUE that counts soft-deleted rows.
 */
it('refuses a code a deleted product still holds', function () {
    Product::factory()->create(['sku' => 'ALM-001'])->delete();

    $this->actingAs($this->owner)
        ->post('/products', productPayload())
        ->assertSessionHasErrors('sku');
});

it('rejects a blank name and writes nothing', function () {
    $this->actingAs($this->owner)
        ->post('/products', productPayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Product::count())->toBe(0);
});

it('rejects a category that does not exist', function () {
    $this->actingAs($this->owner)
        ->post('/products', productPayload(['category_id' => 9999]))
        ->assertSessionHasErrors('category_id');

    expect(Product::count())->toBe(0);
});

it('keeps the category it was filed under', function () {
    $category = ProductCategory::factory()->create(['name' => 'আলমারি']);

    $this->actingAs($this->owner)
        ->post('/products', productPayload(['category_id' => $category->id]));

    expect(Product::sole()->category->name)->toBe('আলমারি');
});

/**
 * Stock on hand is what stock_movements adds up to. A figure typed over it
 * would make the movement log a story nobody can check.
 */
it('ignores stock sent on an edit', function () {
    $product = Product::factory()->inStock('3.00')->create(['sku' => 'ALM-001']);

    $this->actingAs($this->owner)
        ->put("/products/{$product->id}", productPayload([
            'name' => 'সেগুন আলমারি (বড়)',
            'opening_stock' => '99',
        ]));

    expect($product->refresh()->name)->toBe('সেগুন আলমারি (বড়)')
        ->and($product->current_stock)->toBe('3.00')
        ->and(StockMovement::count())->toBe(0);
});

it('soft-deletes a product that was never sold or stocked', function () {
    $product = Product::factory()->create();

    $this->actingAs($this->owner)->delete("/products/{$product->id}");

    expect(Product::find($product->id))->toBeNull()
        ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('refuses to delete a product that has been sold', function () {
    $product = Product::factory()->create();
    $sale = Sale::factory()->create();
    SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);

    $this->actingAs($this->owner)
        ->delete("/products/{$product->id}")
        ->assertSessionHas('error');

    expect(Product::find($product->id))->not->toBeNull();
});

it('refuses to delete a product still standing on the floor', function () {
    $product = Product::factory()->inStock('2.00')->create();

    $this->actingAs($this->owner)
        ->delete("/products/{$product->id}")
        ->assertSessionHas('error');

    expect(Product::find($product->id))->not->toBeNull();
});

it('values the floor at cost', function () {
    Product::factory()->inStock('3.00', '18000.00')->create();
    Product::factory()->inStock('2.00', '1200.00')->create();
    // Switched off, so it is not part of what the shop is carrying.
    Product::factory()->inStock('5.00', '9000.00')->create(['is_active' => false]);

    $this->actingAs($this->owner)
        ->get('/products')
        ->assertInertia(fn ($page) => $page->where('stockValue', '56400.00'));
});

it('counts and filters what has fallen to the reorder line', function () {
    Product::factory()->inStock('1.00')->create(['min_stock' => '2.00']);
    Product::factory()->inStock('9.00')->create(['min_stock' => '2.00']);
    Product::factory()->inStock('0.00')->create(['min_stock' => '0.00']);

    $this->actingAs($this->owner)
        ->get('/products')
        ->assertInertia(fn ($page) => $page->where('lowCount', 1)->has('products.data', 3));

    $this->actingAs($this->owner)
        ->get('/products?low=1')
        ->assertInertia(fn ($page) => $page->where('low', true)->has('products.data', 1));
});

it('finds a product by name, code or wood', function (string $term) {
    Product::factory()->create(['name' => 'সেগুন আলমারি', 'sku' => 'ALM-001', 'wood_type' => 'সেগুন']);
    Product::factory()->create(['name' => 'মেহগনি চেয়ার', 'sku' => 'CHR-009', 'wood_type' => 'মেহগনি']);

    $this->actingAs($this->owner)
        ->get("/products?search={$term}")
        ->assertInertia(fn ($page) => $page->has('products.data', 1));
})->with(['আলমারি', 'ALM-001', 'মেহগনি']);

/**
 * The storekeeper is the one who knows what is on the floor.
 */
it('lets a storekeeper add a product', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/products')->assertOk();
    $this->actingAs($storekeeper)->post('/products', productPayload())->assertRedirect('/products');

    expect(Product::count())->toBe(1);
});

it('keeps a manager to reading the product list', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)->get('/products')->assertOk();
    $this->actingAs($manager)->post('/products', productPayload())->assertForbidden();

    expect(Product::count())->toBe(0);
});
