<?php

use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->shop = Shop::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->material = Material::factory()->create();

    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 100000,
        'current_balance' => 100000,
    ]);
});

/**
 * @return array<string, mixed>
 */
function challanPayload(array $overrides = [], ?array $items = null): array
{
    return array_merge([
        'supplier_id' => test()->supplier->id,
        'shop_id' => test()->shop->id,
        'purchase_date' => '2026-07-20',
        'payment_type' => PurchasePaymentType::Cash->value,
        'account_id' => test()->drawer->id,
        'payment_method' => CashPaymentMethod::Cash->value,
        'transport_cost' => '0',
        'discount' => '0',
        'items' => $items ?? [[
            'item_id' => test()->material->id,
            'quantity' => '10',
            'unit_price' => '1200',
        ]],
    ], $overrides);
}

it('shows the challan book', function () {
    Purchase::factory()->count(3)->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->owner)
        ->get('/purchases')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('purchases.data', 3));
});

it('shows the entry form with what can be bought and paid from', function () {
    $this->actingAs($this->owner)
        ->get('/purchases/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suppliers', 1)
            ->has('materials', 1)
            ->has('accounts', 1)
            ->has('paymentTypes', 3)
        );
});

it('leaves a switched off supplier out of the form', function () {
    Supplier::factory()->inactive()->create();
    Material::factory()->create(['is_active' => false]);

    $this->actingAs($this->owner)
        ->get('/purchases/create')
        ->assertInertia(fn ($page) => $page->has('suppliers', 1)->has('materials', 1));
});

it('records a cash challan and the money leaving', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload())
        ->assertRedirect('/purchases')
        ->assertSessionHas('success');

    expect(Purchase::count())->toBe(1)
        ->and(PurchaseItem::count())->toBe(1)
        ->and(MaterialMovement::count())->toBe(1)
        ->and(SupplierLedger::count())->toBe(2)
        ->and(Transaction::count())->toBe(1)
        ->and(Purchase::sole()->status)->toBe(PurchaseStatus::Paid)
        ->and($this->drawer->refresh()->current_balance)->toBe('88000.00');
});

/**
 * The case the whole design protects, driven through the screen this time.
 */
it('records a credit challan without any money moving', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload([
            'payment_type' => PurchasePaymentType::Credit->value,
            'account_id' => null,
        ]))
        ->assertRedirect('/purchases');

    expect(Transaction::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(1)
        ->and(Purchase::sole()->due_amount)->toBe('12000.00')
        ->and($this->drawer->refresh()->current_balance)->toBe('100000.00');
});

it('demands an account when money is said to have moved', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload(['account_id' => null]))
        ->assertSessionHasErrors('account_id');

    expect(Purchase::count())->toBe(0);
});

it('demands an amount for a partial payment', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload(['payment_type' => PurchasePaymentType::Partial->value]))
        ->assertSessionHasErrors('paid_amount');

    expect(Purchase::count())->toBe(0);
});

it('records a partial payment', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload([
            'payment_type' => PurchasePaymentType::Partial->value,
            'paid_amount' => '5000',
        ]))
        ->assertRedirect('/purchases');

    expect(Purchase::sole()->due_amount)->toBe('7000.00')
        ->and(Purchase::sole()->status)->toBe(PurchaseStatus::Partial)
        ->and(Transaction::sole()->amount)->toBe('5000.00');
});

it('refuses a challan dated in the future', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload(['purchase_date' => now()->addDay()->toDateString()]))
        ->assertSessionHasErrors('purchase_date');

    expect(Purchase::count())->toBe(0);
});

it('refuses a challan with no lines', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload([], []))
        ->assertSessionHasErrors('items');

    expect(Purchase::count())->toBe(0);
});

it('refuses a line for something that is not a material', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload([], [[
            'item_id' => 9999,
            'quantity' => '10',
            'unit_price' => '1200',
        ]]))
        ->assertSessionHasErrors('items.0.item_id');

    expect(Purchase::count())->toBe(0);
});

it('refuses a line that moves nothing', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload([], [[
            'item_id' => $this->material->id,
            'quantity' => '0',
            'unit_price' => '1200',
        ]]))
        ->assertSessionHasErrors('items.0.quantity');

    expect(Purchase::count())->toBe(0);
});

it('refuses a due date before the challan itself', function () {
    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload([
            'payment_type' => PurchasePaymentType::Credit->value,
            'account_id' => null,
            'payment_due_date' => '2026-07-01',
        ]))
        ->assertSessionHasErrors('payment_due_date');

    expect(Purchase::count())->toBe(0);
});

/**
 * A refusal from CashService is a message the counter can act on, not a 500.
 */
it('reports a drawer that cannot cover the challan and writes nothing', function () {
    $thin = Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    $this->actingAs($this->owner)
        ->post('/purchases', challanPayload(['account_id' => $thin->id]))
        ->assertSessionHas('error');

    expect(Purchase::count())->toBe(0)
        ->and(MaterialMovement::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(0);
});

it('filters the book down to what is still owed', function () {
    Purchase::factory()->onCredit('12000.00')->create(['supplier_id' => $this->supplier->id]);
    Purchase::factory()->withTotals('5000.00', '5000.00')->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->owner)
        ->get('/purchases?status=owing')
        ->assertInertia(fn ($page) => $page->has('purchases.data', 1));
});

it('finds a challan by its number or its supplier', function (string $term) {
    $supplier = Supplier::factory()->create(['name' => 'করিম টিম্বার']);
    Purchase::factory()->create(['supplier_id' => $supplier->id, 'purchase_no' => 'PO-2607-0007', 'reference_no' => 'CH-8891']);
    Purchase::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->owner)
        ->get("/purchases?search={$term}")
        ->assertInertia(fn ($page) => $page->has('purchases.data', 1));
})->with(['PO-2607-0007', 'CH-8891', 'করিম']);

it('lets a storekeeper write a challan but keeps an accountant to reading', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($storekeeper)->get('/purchases/create')->assertOk();
    $this->actingAs($accountant)->get('/purchases')->assertOk();
    $this->actingAs($accountant)->get('/purchases/create')->assertForbidden();
    $this->actingAs($accountant)->post('/purchases', challanPayload())->assertForbidden();

    expect(Purchase::count())->toBe(0);
});
