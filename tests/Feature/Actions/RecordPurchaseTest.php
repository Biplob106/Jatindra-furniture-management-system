<?php

use App\Actions\Purchases\RecordPurchase;
use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\LedgerDirection;
use App\Enums\MaterialMovementType;
use App\Enums\MaterialUnit;
use App\Enums\PurchaseItemType;
use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
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
use App\Services\LedgerService;

beforeEach(function () {
    $this->action = app(RecordPurchase::class);
    $this->ledger = app(LedgerService::class);

    $this->shop = Shop::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->material = Material::factory()->create(['unit' => MaterialUnit::Cft]);

    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 100000,
        'current_balance' => 100000,
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @param  list<array<string, mixed>>|null  $items
 */
function buy(array $overrides = [], ?array $items = null, bool $withAccount = true): Purchase
{
    return test()->action->handle(
        data: array_merge([
            'purchase_date' => '2026-07-20',
            'shop_id' => test()->shop->id,
            'payment_type' => PurchasePaymentType::Cash,
        ], $overrides),
        items: $items ?? [[
            'item_type' => PurchaseItemType::Material,
            'item_id' => test()->material->id,
            'quantity' => '10',
            'unit' => MaterialUnit::Cft,
            'unit_price' => '1200',
        ]],
        supplier: test()->supplier,
        account: $withAccount ? test()->drawer : null,
    );
}

/**
 * The case the whole three-ledger design exists to protect. Section 9: a
 * credit purchase writes the operational rows and the supplier ledger credit,
 * and NOTHING to transactions. The stock arrived and the debt is real, but no
 * note left the drawer.
 */
it('writes no transactions row for a credit purchase', function () {
    $purchase = buy(['payment_type' => PurchasePaymentType::Credit]);

    expect(Transaction::count())->toBe(0)
        ->and(Purchase::count())->toBe(1)
        ->and(PurchaseItem::count())->toBe(1)
        ->and(MaterialMovement::count())->toBe(1)
        ->and(SupplierLedger::count())->toBe(1)
        ->and($purchase->due_amount)->toBe('12000.00')
        ->and($purchase->paid_amount)->toBe('0.00')
        ->and($purchase->status)->toBe(PurchaseStatus::Pending);
});

it('leaves the cash box untouched on a credit purchase', function () {
    buy(['payment_type' => PurchasePaymentType::Credit]);

    expect($this->drawer->refresh()->current_balance)->toBe('100000.00');
});

it('records what we owe as a single credit', function () {
    $purchase = buy(['payment_type' => PurchasePaymentType::Credit]);

    $entry = SupplierLedger::sole();

    expect($entry->direction)->toBe(LedgerDirection::Credit)
        ->and($entry->type)->toBe(SupplierLedgerEntryType::Purchase)
        ->and($entry->amount)->toBe('12000.00')
        ->and($entry->reference_type)->toBe(Purchase::class)
        ->and($entry->reference_id)->toBe($purchase->id)
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('12000.00');
});

/**
 * A cash purchase carries the credit and the matching debit, so the supplier's
 * history reads as a challan and a payment rather than as nothing at all.
 */
it('writes a credit and a debit and one transaction for a cash purchase', function () {
    $purchase = buy(['payment_type' => PurchasePaymentType::Cash]);

    expect(SupplierLedger::count())->toBe(2)
        ->and(SupplierLedger::credits()->sum('amount'))->toEqual('12000.00')
        ->and(SupplierLedger::debits()->sum('amount'))->toEqual('12000.00')
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('0.00')
        ->and(Transaction::count())->toBe(1)
        ->and($purchase->status)->toBe(PurchaseStatus::Paid)
        ->and($purchase->due_amount)->toBe('0.00');
});

it('takes the money out of the account it was paid from', function () {
    $purchase = buy();

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(TransactionDirection::Out)
        ->and($transaction->amount)->toBe('12000.00')
        ->and($transaction->source_type)->toBe(TransactionSource::PurchasePayment)
        ->and($transaction->source_id)->toBe($purchase->id)
        ->and($transaction->party_type)->toBe(Supplier::class)
        ->and($transaction->party_id)->toBe($this->supplier->id)
        ->and($transaction->shop_id)->toBe($this->shop->id)
        ->and($this->drawer->refresh()->current_balance)->toBe('88000.00');
});

it('splits a partial payment between the drawer and the debt', function () {
    $purchase = buy([
        'payment_type' => PurchasePaymentType::Partial,
        'paid_amount' => '5000',
    ]);

    expect($purchase->paid_amount)->toBe('5000.00')
        ->and($purchase->due_amount)->toBe('7000.00')
        ->and($purchase->status)->toBe(PurchaseStatus::Partial)
        ->and(Transaction::sole()->amount)->toBe('5000.00')
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('7000.00')
        ->and($this->drawer->refresh()->current_balance)->toBe('95000.00');
});

/**
 * A partial payment covering all or none of the challan is a cash or credit
 * purchase under the wrong name, and payment_type is what the payable report
 * reads.
 */
it('refuses a partial payment that is not partial', function (string $paid) {
    expect(fn () => buy([
        'payment_type' => PurchasePaymentType::Partial,
        'paid_amount' => $paid,
    ]))->toThrow(InvalidArgumentException::class);

    expect(Purchase::count())->toBe(0);
})->with(['0', '12000', '15000']);

it('prices the lines itself and ignores totals sent by the client', function () {
    $purchase = buy(['payment_type' => PurchasePaymentType::Credit], [
        [
            'item_type' => PurchaseItemType::Material,
            'item_id' => $this->material->id,
            'quantity' => '10.500',
            'unit_price' => '1200',
            'line_total' => '1.00',
        ],
    ]);

    expect(PurchaseItem::sole()->line_total)->toBe('12600.00')
        ->and($purchase->subtotal)->toBe('12600.00')
        ->and($purchase->total_amount)->toBe('12600.00');
});

it('adds transport and takes off the discount', function () {
    $purchase = buy([
        'payment_type' => PurchasePaymentType::Credit,
        'transport_cost' => '800',
        'discount' => '300',
    ]);

    expect($purchase->subtotal)->toBe('12000.00')
        ->and($purchase->transport_cost)->toBe('800.00')
        ->and($purchase->discount)->toBe('300.00')
        ->and($purchase->total_amount)->toBe('12500.00')
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('12500.00');
});

it('refuses a discount larger than the challan', function () {
    expect(fn () => buy([
        'payment_type' => PurchasePaymentType::Credit,
        'discount' => '20000',
    ]))->toThrow(InvalidArgumentException::class);

    expect(Purchase::count())->toBe(0);
});

it('sums several lines to the paisa', function () {
    $hinges = Material::factory()->create(['unit' => MaterialUnit::Piece]);

    $purchase = buy(['payment_type' => PurchasePaymentType::Credit], [
        [
            'item_type' => PurchaseItemType::Material,
            'item_id' => $this->material->id,
            'quantity' => '3.250',
            'unit_price' => '1234.57',
        ],
        [
            'item_type' => PurchaseItemType::Material,
            'item_id' => $hinges->id,
            'quantity' => '24',
            'unit_price' => '45.75',
        ],
    ]);

    // 3.250 * 1234.57 = 4012.35 (truncated), 24 * 45.75 = 1098.00
    expect(PurchaseItem::count())->toBe(2)
        ->and($purchase->subtotal)->toBe('5110.35')
        ->and($purchase->total_amount)->toBe('5110.35')
        ->and(MaterialMovement::count())->toBe(2);
});

it('brings the stock in', function () {
    buy(['payment_type' => PurchasePaymentType::Credit]);

    $movement = MaterialMovement::sole();

    expect($movement->type)->toBe(MaterialMovementType::In)
        ->and($movement->quantity)->toBe('10.000')
        ->and($movement->unit_cost)->toBe('1200.00')
        ->and($movement->reference_type)->toBe(Purchase::class)
        ->and($this->material->refresh()->current_stock)->toBe('10.000');
});

/**
 * What the shop paid this time and what it already had, over the total.
 */
it('moves the average cost with the price paid', function () {
    $material = Material::factory()->inStock('10.000', '1000.00')->create();

    buy(['payment_type' => PurchasePaymentType::Credit], [[
        'item_type' => PurchaseItemType::Material,
        'item_id' => $material->id,
        'quantity' => '10',
        'unit_price' => '1400.00',
    ]]);

    expect($material->refresh()->current_stock)->toBe('20.000')
        ->and($material->avg_cost)->toBe('1200.00');
});

it('takes the price paid as the average for a material with no stock', function () {
    buy(['payment_type' => PurchasePaymentType::Credit]);

    expect($this->material->refresh()->avg_cost)->toBe('1200.00');
});

it('issues a printable purchase number', function () {
    $first = buy(['payment_type' => PurchasePaymentType::Credit]);
    $second = buy(['payment_type' => PurchasePaymentType::Credit]);

    expect($first->purchase_no)->toBe('PO-2607-0001')
        ->and($second->purchase_no)->toBe('PO-2607-0002');
});

it('dates the credit terms from the supplier when the form says nothing', function () {
    $supplier = Supplier::factory()->onCredit(30)->create();
    $this->supplier = $supplier;

    $purchase = buy(['payment_type' => PurchasePaymentType::Credit]);

    expect($purchase->payment_due_date->toDateString())->toBe('2026-08-19');
});

it('keeps the due date the form gave', function () {
    $purchase = buy([
        'payment_type' => PurchasePaymentType::Credit,
        'payment_due_date' => '2026-09-01',
    ]);

    expect($purchase->payment_due_date->toDateString())->toBe('2026-09-01');
});

it('leaves a fully paid challan with no due date', function () {
    $supplier = Supplier::factory()->onCredit(30)->create();
    $this->supplier = $supplier;

    expect(buy()->payment_due_date)->toBeNull();
});

it('records who bought it and the challan number', function () {
    $user = User::factory()->create();

    $purchase = $this->action->handle(
        data: [
            'purchase_date' => '2026-07-20',
            'payment_type' => PurchasePaymentType::Credit,
            'reference_no' => 'CH-8891',
            'note' => 'সেগুন কাঠ',
        ],
        items: [[
            'item_type' => PurchaseItemType::Material,
            'item_id' => $this->material->id,
            'quantity' => '10',
            'unit_price' => '1200',
        ]],
        supplier: $this->supplier,
        userId: $user->id,
    );

    expect($purchase->created_by)->toBe($user->id)
        ->and($purchase->reference_no)->toBe('CH-8891')
        ->and($purchase->note)->toBe('সেগুন কাঠ')
        ->and(SupplierLedger::sole()->created_by)->toBe($user->id);
});

it('records the method the money left by', function () {
    $bank = Account::factory()->create([
        'type' => AccountType::Bank,
        'shop_id' => $this->shop->id,
        'opening_balance' => 50000,
        'current_balance' => 50000,
    ]);

    $this->action->handle(
        data: [
            'purchase_date' => '2026-07-20',
            'payment_type' => PurchasePaymentType::Cash,
            'payment_method' => CashPaymentMethod::Cheque,
        ],
        items: [[
            'item_type' => PurchaseItemType::Material,
            'item_id' => $this->material->id,
            'quantity' => '10',
            'unit_price' => '1200',
        ]],
        supplier: $this->supplier,
        account: $bank,
    );

    expect(Transaction::sole()->payment_method)->toBe(CashPaymentMethod::Cheque)
        ->and($bank->refresh()->current_balance)->toBe('38000.00');
});

/**
 * A refusal takes the whole challan with it rather than leaving stock that was
 * never paid for.
 */
it('persists nothing when the drawer cannot cover the payment', function () {
    $thin = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    expect(fn () => $this->action->handle(
        data: ['purchase_date' => '2026-07-20', 'payment_type' => PurchasePaymentType::Cash],
        items: [[
            'item_type' => PurchaseItemType::Material,
            'item_id' => $this->material->id,
            'quantity' => '10',
            'unit_price' => '1200',
        ]],
        supplier: $this->supplier,
        account: $thin,
    ))->toThrow(RuntimeException::class);

    expect(Purchase::count())->toBe(0)
        ->and(PurchaseItem::count())->toBe(0)
        ->and(MaterialMovement::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($this->material->refresh()->current_stock)->toBe('0.000')
        ->and($thin->refresh()->current_balance)->toBe('500.00');
});

it('refuses to pay without an account for the money to leave', function () {
    expect(fn () => buy([], null, withAccount: false))
        ->toThrow(InvalidArgumentException::class);

    expect(Purchase::count())->toBe(0);
});

it('refuses a challan with no lines', function () {
    expect(fn () => buy(['payment_type' => PurchasePaymentType::Credit], []))
        ->toThrow(InvalidArgumentException::class);

    expect(Purchase::count())->toBe(0);
});

it('refuses a line that moves nothing', function (string $quantity) {
    expect(fn () => buy(['payment_type' => PurchasePaymentType::Credit], [[
        'item_type' => PurchaseItemType::Material,
        'item_id' => $this->material->id,
        'quantity' => $quantity,
        'unit_price' => '1200',
    ]]))->toThrow(InvalidArgumentException::class);

    expect(Purchase::count())->toBe(0)
        ->and(MaterialMovement::count())->toBe(0);
})->with(['0', '-5']);

/**
 * products arrives in phase 6 with its own stock table. Writing a material
 * movement against a product id would corrupt both.
 */
it('refuses a readymade product line until phase 6', function () {
    expect(fn () => buy(['payment_type' => PurchasePaymentType::Credit], [[
        'item_type' => PurchaseItemType::Product,
        'item_id' => 1,
        'quantity' => '2',
        'unit_price' => '5000',
    ]]))->toThrow(InvalidArgumentException::class);

    expect(Purchase::count())->toBe(0);
});

it('keeps two suppliers apart', function () {
    $other = Supplier::factory()->create();

    buy(['payment_type' => PurchasePaymentType::Credit]);

    $this->supplier = $other;
    buy(['payment_type' => PurchasePaymentType::Credit], [[
        'item_type' => PurchaseItemType::Material,
        'item_id' => $this->material->id,
        'quantity' => '1',
        'unit_price' => '500',
    ]]);

    expect($this->ledger->supplierBalanceFor($this->supplier))->toBe('500.00')
        ->and(Purchase::count())->toBe(2);
});
