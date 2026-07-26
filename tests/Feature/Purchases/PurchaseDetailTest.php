<?php

use App\Actions\Purchases\PaySupplier;
use App\Actions\Purchases\RecordPurchase;
use App\Enums\AccountType;
use App\Enums\PurchasePaymentType;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Material;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->shop = Shop::factory()->create();
    $this->supplier = Supplier::factory()->create(['name' => 'করিম টিম্বার']);
    $this->timber = Material::factory()->create(['name' => 'সেগুন কাঠ']);
    $this->hinges = Material::factory()->create(['name' => 'পিতলের কব্জা']);

    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 100000,
        'current_balance' => 100000,
    ]);
});

/**
 * A real challan, written the way the screen writes one.
 */
function creditChallan(string $date = '2026-07-20'): Purchase
{
    return app(RecordPurchase::class)->handle(
        data: [
            'purchase_date' => $date,
            'shop_id' => test()->shop->id,
            'payment_type' => PurchasePaymentType::Credit,
            'reference_no' => 'CH-8891',
            'note' => 'গাড়িতে এসেছে',
        ],
        items: [
            ['item_id' => test()->timber->id, 'quantity' => '10', 'unit' => 'cft', 'unit_price' => '1200'],
            ['item_id' => test()->hinges->id, 'quantity' => '24', 'unit' => 'piece', 'unit_price' => '45.75'],
        ],
        supplier: test()->supplier,
        userId: test()->owner->id,
    );
}

it('shows a challan with its lines named', function () {
    $purchase = creditChallan();

    $this->actingAs($this->owner)
        ->get("/purchases/{$purchase->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('purchase.purchase_no', $purchase->purchase_no)
            ->where('purchase.reference_no', 'CH-8891')
            ->where('purchase.total_amount', '13098.00')
            ->where('purchase.due_amount', '13098.00')
            ->where('purchase.supplier.name', 'করিম টিম্বার')
            ->has('items', 2)
            ->where('items.0.name', 'সেগুন কাঠ')
            ->where('items.0.line_total', '12000.00')
            ->where('items.1.name', 'পিতলের কব্জা')
        );
});

/**
 * A credit challan starts with nothing against it. That is the point of it.
 */
it('shows no payments against a fresh credit challan', function () {
    $purchase = creditChallan();

    $this->actingAs($this->owner)
        ->get("/purchases/{$purchase->id}")
        ->assertInertia(fn ($page) => $page->has('payments', 0));
});

it('shows what has been paid against the challan', function () {
    $purchase = creditChallan();

    app(PaySupplier::class)->handle(
        supplier: $this->supplier,
        account: $this->drawer,
        data: ['amount' => '5000', 'payment_date' => '2026-07-22', 'reference_no' => 'CHQ-11'],
    );

    $this->actingAs($this->owner)
        ->get("/purchases/{$purchase->id}")
        ->assertInertia(fn ($page) => $page
            ->has('payments', 1)
            ->where('payments.0.allocated_amount', '5000.00')
            ->where('payments.0.payment_total', '5000.00')
            ->where('payments.0.payment_date', '2026-07-22')
            ->where('payments.0.reference_no', 'CHQ-11')
            ->where('purchase.due_amount', '8098.00')
        );
});

/**
 * One handover can settle several challans, so the screen says what landed
 * here against what was handed over in total.
 */
it('separates what landed here from the whole handover', function () {
    $first = creditChallan('2026-07-01');
    $second = creditChallan('2026-07-05');

    app(PaySupplier::class)->handle(
        supplier: $this->supplier,
        account: $this->drawer,
        data: ['amount' => '20000', 'payment_date' => '2026-07-22'],
    );

    $this->actingAs($this->owner)
        ->get("/purchases/{$second->id}")
        ->assertInertia(fn ($page) => $page
            ->has('payments', 1)
            // 20000 handed over, 13098 cleared the older challan first.
            ->where('payments.0.allocated_amount', '6902.00')
            ->where('payments.0.payment_total', '20000.00')
        );

    expect($first->refresh()->due_amount)->toBe('0.00');
});

it('lists several payments newest first', function () {
    $purchase = creditChallan('2026-07-01');

    foreach (['2026-07-10', '2026-07-20'] as $date) {
        app(PaySupplier::class)->handle(
            supplier: $this->supplier,
            account: $this->drawer,
            data: ['amount' => '2000', 'payment_date' => $date],
        );
    }

    $this->actingAs($this->owner)
        ->get("/purchases/{$purchase->id}")
        ->assertInertia(fn ($page) => $page
            ->has('payments', 2)
            ->where('payments.0.payment_date', '2026-07-20')
            ->where('payments.1.payment_date', '2026-07-10')
        );
});

it('does not read "create" as a challan id', function () {
    $this->actingAs($this->owner)->get('/purchases/create')->assertOk();
});

it('keeps someone without the read permission out', function () {
    $purchase = creditChallan();

    $user = User::factory()->create();

    $this->actingAs($user)->get("/purchases/{$purchase->id}")->assertForbidden();
});

it('lets an accountant read a challan they cannot write', function () {
    $purchase = creditChallan();

    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)->get("/purchases/{$purchase->id}")->assertOk();
});
