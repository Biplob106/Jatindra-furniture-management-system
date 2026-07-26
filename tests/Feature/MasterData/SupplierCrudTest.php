<?php

use App\Enums\LedgerDirection;
use App\Enums\Role;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\SupplierType;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->ledger = app(LedgerService::class);
});

/**
 * @return array<string, mixed>
 */
function supplierPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'করিম টিম্বার',
        'business_name' => 'করিম স-মিল',
        'phone' => '01711111111',
        'address' => 'রংপুর',
        'supplier_type' => SupplierType::Wood->value,
        'opening_due' => '0',
        'credit_limit' => '50000',
        'default_credit_days' => '30',
        'is_active' => true,
    ], $overrides);
}

it('shows the list to the owner', function () {
    Supplier::factory()->count(3)->create();

    $this->actingAs($this->owner)->get('/suppliers')->assertOk();
});

it('creates exactly one supplier', function () {
    $this->actingAs($this->owner)
        ->post('/suppliers', supplierPayload())
        ->assertRedirect('/suppliers');

    expect(Supplier::count())->toBe(1)
        ->and(Supplier::sole()->default_credit_days)->toBe(30)
        ->and(SupplierLedger::count())->toBe(0);
});

/**
 * The opening figure is stored on the row for reference, but the money only
 * counts once it is a ledger credit: balances are SUM(credit) - SUM(debit)
 * and nothing else may contribute to them.
 */
it('seeds an opening due as a ledger credit', function () {
    $this->actingAs($this->owner)
        ->post('/suppliers', supplierPayload(['opening_due' => '18000']))
        ->assertRedirect('/suppliers');

    $supplier = Supplier::sole();
    $entry = SupplierLedger::sole();

    expect($entry->type)->toBe(SupplierLedgerEntryType::Opening)
        ->and($entry->direction)->toBe(LedgerDirection::Credit)
        ->and($entry->amount)->toBe('18000.00')
        ->and($this->ledger->supplierBalanceFor($supplier))->toBe('18000.00');
});

it('writes no ledger row for a supplier who was owed nothing', function () {
    $this->actingAs($this->owner)->post('/suppliers', supplierPayload(['opening_due' => '0']));

    expect(SupplierLedger::count())->toBe(0)
        ->and($this->ledger->supplierBalanceFor(Supplier::sole()))->toBe('0.00');
});

it('rejects a blank name and writes nothing', function () {
    $this->actingAs($this->owner)
        ->post('/suppliers', supplierPayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Supplier::count())->toBe(0);
});

it('rejects a negative opening due', function () {
    $this->actingAs($this->owner)
        ->post('/suppliers', supplierPayload(['opening_due' => '-500']))
        ->assertSessionHasErrors('opening_due');

    expect(Supplier::count())->toBe(0);
});

it('edits a supplier without touching the ledger', function () {
    $supplier = Supplier::factory()->create(['opening_due' => 5000]);
    $this->ledger->recordSupplier(
        supplier: $supplier,
        type: SupplierLedgerEntryType::Opening,
        amount: '5000.00',
        entryDate: '2026-07-01',
        direction: LedgerDirection::Credit,
    );

    $this->actingAs($this->owner)
        ->put("/suppliers/{$supplier->id}", supplierPayload(['name' => 'করিম ট্রেডার্স']))
        ->assertRedirect('/suppliers');

    expect($supplier->refresh()->name)->toBe('করিম ট্রেডার্স')
        ->and(SupplierLedger::count())->toBe(1)
        ->and($this->ledger->supplierBalanceFor($supplier))->toBe('5000.00');
});

/**
 * The opening figure is a day-one number. Correcting it later is an adjustment
 * entry, not an edit to the field, so the form does not send it and the
 * request does not accept it.
 */
it('ignores an opening due sent on an edit', function () {
    $supplier = Supplier::factory()->create(['opening_due' => 5000]);

    $this->actingAs($this->owner)
        ->put("/suppliers/{$supplier->id}", supplierPayload(['opening_due' => '99000']));

    expect($supplier->refresh()->opening_due)->toBe('5000.00')
        ->and(SupplierLedger::count())->toBe(0);
});

it('soft-deletes a supplier with nothing behind them', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->owner)->delete("/suppliers/{$supplier->id}");

    expect(Supplier::find($supplier->id))->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
});

it('refuses to delete a supplier with challans behind them', function () {
    $supplier = Supplier::factory()->create();
    Purchase::factory()->withTotals('5000.00', '5000.00')->create(['supplier_id' => $supplier->id]);

    $this->actingAs($this->owner)
        ->delete("/suppliers/{$supplier->id}")
        ->assertSessionHas('error');

    expect(Supplier::find($supplier->id))->not->toBeNull();
});

/**
 * Both directions: we may owe them, or we may have paid ahead. Either is money
 * with a name on it.
 */
it('refuses to delete a supplier whose balance is not zero', function (string $direction) {
    $supplier = Supplier::factory()->create();

    $this->ledger->recordSupplier(
        supplier: $supplier,
        type: SupplierLedgerEntryType::Adjustment,
        amount: '2000.00',
        entryDate: '2026-07-01',
        direction: LedgerDirection::from($direction),
    );

    $this->actingAs($this->owner)
        ->delete("/suppliers/{$supplier->id}")
        ->assertSessionHas('error');

    expect(Supplier::find($supplier->id))->not->toBeNull();
})->with(['credit', 'debit']);

it('finds a supplier by name, shop or phone', function (string $term) {
    Supplier::factory()->create([
        'name' => 'করিম টিম্বার',
        'business_name' => 'করিম স-মিল',
        'phone' => '01711111111',
    ]);
    Supplier::factory()->create(['name' => 'রহিম হার্ডওয়্যার', 'business_name' => null, 'phone' => '01822222222']);

    $this->actingAs($this->owner)
        ->get("/suppliers?search={$term}")
        ->assertInertia(fn ($page) => $page->has('suppliers.data', 1));
})->with(['করিম', 'স-মিল', '017']);

it('carries each supplier balance onto the list', function () {
    $owed = Supplier::factory()->create();
    Supplier::factory()->create();

    $this->ledger->recordSupplier($owed, SupplierLedgerEntryType::Purchase, '12000.00', '2026-07-01');

    $this->actingAs($this->owner)
        ->get('/suppliers')
        ->assertInertia(fn ($page) => $page
            ->where('suppliers.data.0.balance', fn ($balance) => in_array($balance, ['0.00', '12000.00'], true))
            ->has('suppliers.data', 2)
        );
});

it('keeps a storekeeper out of editing suppliers', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    // A storekeeper reads the supplier list to know where stock came from.
    $this->actingAs($storekeeper)->get('/suppliers')->assertOk();
    $this->actingAs($storekeeper)->get('/suppliers/create')->assertForbidden();
    $this->actingAs($storekeeper)->post('/suppliers', supplierPayload())->assertForbidden();

    expect(Supplier::count())->toBe(0);
});
