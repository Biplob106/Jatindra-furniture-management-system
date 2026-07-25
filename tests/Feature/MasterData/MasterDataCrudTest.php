<?php

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Employee;
use App\Models\ExpenseCategory;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

/**
 * The whole surface at once: every list is reachable by the owner, every
 * create writes one row, every delete soft-deletes.
 */
dataset('resources', [
    'shops' => ['shops', Shop::class, ['name' => 'নতুন দোকান', 'monthly_rent' => 12000, 'is_active' => true]],
    'trades' => ['trades', Trade::class, ['name' => 'পালিশ', 'default_daily_rate' => 650, 'is_active' => true]],
    'expense-categories' => ['expense-categories', ExpenseCategory::class, ['name' => 'গ্যাস বিল', 'is_recurring' => true, 'is_active' => true]],
    'product-categories' => ['product-categories', ProductCategory::class, ['name' => 'শোকেস', 'is_active' => true]],
]);

it('shows the list to the owner', function (string $uri) {
    $this->actingAs($this->owner)->get("/{$uri}")->assertOk();
})->with([['shops'], ['trades'], ['accounts'], ['expense-categories'], ['product-categories']]);

it('creates exactly one row', function (string $uri, string $model, array $payload) {
    $before = $model::count();

    $this->actingAs($this->owner)->post("/{$uri}", $payload)->assertRedirect("/{$uri}");

    expect($model::count())->toBe($before + 1);
})->with('resources');

it('rejects a blank name and writes nothing', function (string $uri, string $model, array $payload) {
    $before = $model::count();

    $this->actingAs($this->owner)
        ->post("/{$uri}", [...$payload, 'name' => ''])
        ->assertSessionHasErrors('name');

    expect($model::count())->toBe($before);
})->with('resources');

it('soft-deletes rather than removing the row', function (string $uri, string $model, array $payload) {
    $record = $model::factory()->create();

    $this->actingAs($this->owner)->delete("/{$uri}/{$record->id}");

    expect($model::find($record->id))->toBeNull()
        ->and($model::withTrashed()->find($record->id))->not->toBeNull();
})->with('resources');

it('hides every master data screen from a storekeeper who lacks the permission', function (string $uri) {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get("/{$uri}")->assertForbidden();
})->with([['trades'], ['accounts'], ['expense-categories'], ['product-categories']]);

it('lets an accountant read accounts but a manager not at all', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($accountant)->get('/accounts')->assertOk();
    $this->actingAs($manager)->get('/accounts')->assertForbidden();
});

it('stops a manager from editing trades they can read', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    // manager holds trades.view and trades.manage, so this one is allowed.
    $this->actingAs($manager)->get('/trades')->assertOk();
    $this->actingAs($manager)->get('/trades/create')->assertOk();

    // Shops are read-only for a manager.
    $shop = Shop::factory()->create();
    $this->actingAs($manager)->get('/shops')->assertOk();
    $this->actingAs($manager)->get("/shops/{$shop->id}/edit")->assertForbidden();
    $this->actingAs($manager)->delete("/shops/{$shop->id}")->assertForbidden();
});

it('refuses to delete a shop that still has staff', function () {
    $shop = Shop::factory()->create();
    Employee::factory()->create(['shop_id' => $shop->id]);

    $this->actingAs($this->owner)
        ->delete("/shops/{$shop->id}")
        ->assertSessionHas('error');

    expect(Shop::find($shop->id))->not->toBeNull();
});

it('refuses to delete a trade that still has employees', function () {
    $trade = Trade::factory()->create();
    Employee::factory()->create(['trade_id' => $trade->id]);

    $this->actingAs($this->owner)
        ->delete("/trades/{$trade->id}")
        ->assertSessionHas('error');

    expect(Trade::find($trade->id))->not->toBeNull();
});

it('refuses to delete a category that still has children', function () {
    $parent = ProductCategory::factory()->create();
    ProductCategory::factory()->create(['parent_id' => $parent->id]);

    $this->actingAs($this->owner)
        ->delete("/product-categories/{$parent->id}")
        ->assertSessionHas('error');

    expect(ProductCategory::find($parent->id))->not->toBeNull();
});

it('will not let a category be its own parent', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->owner)
        ->put("/product-categories/{$category->id}", [
            'name' => $category->name,
            'parent_id' => $category->id,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($category->fresh()->parent_id)->toBeNull();
});

it('seeds a new account current_balance from its opening balance', function () {
    $this->actingAs($this->owner)->post('/accounts', [
        'name' => 'বিকাশ',
        'type' => AccountType::MobileBanking->value,
        'opening_balance' => 5000,
        'is_active' => true,
    ]);

    $account = Account::where('name', 'বিকাশ')->first();

    expect($account->opening_balance)->toBe('5000.00')
        ->and($account->current_balance)->toBe('5000.00');
});

it('never lets an edit touch current_balance', function () {
    $account = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 7500]);

    $this->actingAs($this->owner)->put("/accounts/{$account->id}", [
        'name' => 'বদলানো নাম',
        'type' => AccountType::Cash->value,
        'is_active' => true,
        // Both of these must be ignored.
        'current_balance' => 999999,
        'opening_balance' => 999999,
    ]);

    $account->refresh();

    expect($account->name)->toBe('বদলানো নাম')
        ->and($account->current_balance)->toBe('7500.00')
        ->and($account->opening_balance)->toBe('1000.00');
});

it('refuses to delete an account holding money', function () {
    $account = Account::factory()->create(['current_balance' => 250.50]);

    $this->actingAs($this->owner)
        ->delete("/accounts/{$account->id}")
        ->assertSessionHas('error');

    expect(Account::find($account->id))->not->toBeNull();
});

it('deletes an empty account', function () {
    $account = Account::factory()->create(['current_balance' => 0]);

    $this->actingAs($this->owner)->delete("/accounts/{$account->id}");

    expect(Account::find($account->id))->toBeNull()
        ->and(Account::withTrashed()->find($account->id))->not->toBeNull();
});

it('finds a shop by name through search', function () {
    Shop::factory()->create(['name' => 'জতীন্দ্র ফার্নিচার']);
    Shop::factory()->create(['name' => 'অন্য দোকান']);

    $this->actingAs($this->owner)
        ->get('/shops?search=জতীন্দ্র')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('shops.data', 1)->where('shops.data.0.name', 'জতীন্দ্র ফার্নিচার'));
});
