<?php

use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\Role;
use App\Enums\SupplierLedgerEntryType;
use App\Models\Account;
use App\Models\PartyPayment;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Queries\SupplierPayableAging;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->ledger = app(LedgerService::class);
    $this->aging = app(SupplierPayableAging::class);

    $this->supplier = Supplier::factory()->create(['name' => 'করিম টিম্বার']);

    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 100000,
        'current_balance' => 100000,
    ]);
});

/**
 * A challan on the books and the ledger credit behind it.
 */
function owedChallan(string $total, string $date, ?Supplier $supplier = null): Purchase
{
    $supplier ??= test()->supplier;

    $purchase = Purchase::factory()->onCredit($total)->create([
        'supplier_id' => $supplier->id,
        'purchase_date' => $date,
    ]);

    test()->ledger->recordSupplier(
        supplier: $supplier,
        type: SupplierLedgerEntryType::Purchase,
        amount: $total,
        entryDate: $date,
        reference: $purchase,
    );

    return $purchase;
}

it('shows who we owe, worst first', function () {
    $small = Supplier::factory()->create();

    owedChallan('12000.00', '2026-07-01');
    owedChallan('3000.00', '2026-07-05', $small);

    $this->actingAs($this->owner)
        ->get('/supplier-ledger')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suppliers', 2)
            ->where('suppliers.0.supplier_id', $this->supplier->id)
            ->where('suppliers.0.due_total', '12000.00')
            ->where('totals.total', '15000.00')
        );
});

it('leaves a settled supplier off the payable list', function () {
    Purchase::factory()->withTotals('5000.00', '5000.00')->create(['supplier_id' => $this->supplier->id]);

    $this->actingAs($this->owner)
        ->get('/supplier-ledger')
        ->assertInertia(fn ($page) => $page->has('suppliers', 0)->where('totals.total', '0.00'));
});

/**
 * Age is counted from the challan date, which is how long the money has sat
 * with us rather than whether we are late.
 */
it('ages what is owed into buckets', function () {
    $asOf = '2026-07-26';

    owedChallan('1000.00', '2026-07-20');   // 6 days
    owedChallan('2000.00', '2026-06-20');   // 36 days
    owedChallan('4000.00', '2026-05-20');   // 67 days
    owedChallan('8000.00', '2026-01-20');   // 187 days

    $totals = $this->aging->totals($asOf);

    expect($totals['current'])->toBe('1000.00')
        ->and($totals['days31'])->toBe('2000.00')
        ->and($totals['days61'])->toBe('4000.00')
        ->and($totals['days90plus'])->toBe('8000.00')
        ->and($totals['total'])->toBe('15000.00');
});

it('puts a challan on its bucket edge in exactly one bucket', function () {
    $asOf = '2026-07-31';

    owedChallan('1000.00', '2026-07-01');   // 30 days
    owedChallan('2000.00', '2026-06-30');   // 31 days

    $totals = $this->aging->totals($asOf);

    expect($totals['current'])->toBe('1000.00')
        ->and($totals['days31'])->toBe('2000.00')
        // The buckets add up to the total, with nothing counted twice.
        ->and(bcadd($totals['current'], $totals['days31'], 2))->toBe($totals['total']);
});

it('counts only the unpaid part of a part-paid challan', function () {
    Purchase::factory()->withTotals('10000.00', '4000.00')->create([
        'supplier_id' => $this->supplier->id,
        'purchase_date' => '2026-07-20',
    ]);

    expect($this->aging->totals('2026-07-26')['total'])->toBe('6000.00');
});

it('refuses to age against something that is not a date', function () {
    expect(fn () => $this->aging->totals("2026-07-26' OR '1"))
        ->toThrow(InvalidArgumentException::class);
});

it('shows one supplier book with its open challans', function () {
    owedChallan('12000.00', '2026-07-01');
    owedChallan('3000.00', '2026-07-05');

    $this->actingAs($this->owner)
        ->get("/supplier-ledger/{$this->supplier->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('balance', '15000.00')
            ->has('openChallans', 2)
            ->has('entries.data', 2)
            // Oldest first, the order a payment clears them in.
            ->where('openChallans.0.purchase_date', '2026-07-01')
        );
});

it('records a payment and clears the oldest challan', function () {
    $july = owedChallan('10000.00', '2026-07-01');
    $later = owedChallan('8000.00', '2026-07-15');

    $this->actingAs($this->owner)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '12000',
            'payment_date' => '2026-07-20',
            'account_id' => $this->drawer->id,
            'payment_method' => CashPaymentMethod::Cash->value,
        ])
        ->assertRedirect("/supplier-ledger/{$this->supplier->id}")
        ->assertSessionHas('success');

    expect(PartyPayment::count())->toBe(1)
        ->and(PaymentAllocation::count())->toBe(2)
        ->and(Transaction::count())->toBe(1)
        ->and($july->refresh()->status)->toBe(PurchaseStatus::Paid)
        ->and($later->refresh()->due_amount)->toBe('6000.00')
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('6000.00')
        ->and($this->drawer->refresh()->current_balance)->toBe('88000.00');
});

it('refuses a payment of nothing', function () {
    owedChallan('10000.00', '2026-07-01');

    $this->actingAs($this->owner)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '0',
            'payment_date' => '2026-07-20',
            'account_id' => $this->drawer->id,
        ])
        ->assertSessionHasErrors('amount');

    expect(PartyPayment::count())->toBe(0);
});

it('refuses a payment dated in the future', function () {
    owedChallan('10000.00', '2026-07-01');

    $this->actingAs($this->owner)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '1000',
            'payment_date' => now()->addDay()->toDateString(),
            'account_id' => $this->drawer->id,
        ])
        ->assertSessionHasErrors('payment_date');

    expect(PartyPayment::count())->toBe(0);
});

/**
 * A drawer that cannot cover it is a message the counter can act on, and
 * nothing is written.
 */
it('reports a drawer that cannot cover the payment', function () {
    $thin = Account::factory()->create([
        'type' => AccountType::Cash,
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    $purchase = owedChallan('10000.00', '2026-07-01');

    $this->actingAs($this->owner)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '10000',
            'payment_date' => '2026-07-20',
            'account_id' => $thin->id,
        ])
        ->assertSessionHas('error');

    expect(PartyPayment::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(1)
        ->and($purchase->refresh()->due_amount)->toBe('10000.00');
});

it('reports an allocation that does not add up', function () {
    $purchase = owedChallan('10000.00', '2026-07-01');

    $this->actingAs($this->owner)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '5000',
            'payment_date' => '2026-07-20',
            'account_id' => $this->drawer->id,
            'allocations' => [$purchase->id => '9000'],
        ])
        ->assertSessionHas('error');

    expect(PartyPayment::count())->toBe(0)
        ->and($purchase->refresh()->due_amount)->toBe('10000.00');
});

it('settles the challans the form picked', function () {
    $older = owedChallan('10000.00', '2026-07-01');
    $newer = owedChallan('8000.00', '2026-07-15');

    $this->actingAs($this->owner)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '8000',
            'payment_date' => '2026-07-20',
            'account_id' => $this->drawer->id,
            'allocations' => [$newer->id => '8000'],
        ])
        ->assertRedirect("/supplier-ledger/{$this->supplier->id}");

    expect($newer->refresh()->status)->toBe(PurchaseStatus::Paid)
        ->and($older->refresh()->due_amount)->toBe('10000.00');
});

it('keeps a storekeeper from handing money over', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    owedChallan('10000.00', '2026-07-01');

    // A storekeeper has no supplier_ledger.view either: what is owed is the
    // bookkeeper's business, not the store room's.
    $this->actingAs($storekeeper)->get('/supplier-ledger')->assertForbidden();
    $this->actingAs($storekeeper)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '1000',
            'payment_date' => '2026-07-20',
            'account_id' => $this->drawer->id,
        ])
        ->assertForbidden();

    expect(PartyPayment::count())->toBe(0);
});

it('lets a manager read what is owed but not pay it', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    owedChallan('10000.00', '2026-07-01');

    $this->actingAs($manager)->get('/supplier-ledger')->assertOk();
    $this->actingAs($manager)
        ->post("/supplier-ledger/{$this->supplier->id}", [
            'amount' => '1000',
            'payment_date' => '2026-07-20',
            'account_id' => $this->drawer->id,
        ])
        ->assertForbidden();

    expect(PartyPayment::count())->toBe(0);
});
