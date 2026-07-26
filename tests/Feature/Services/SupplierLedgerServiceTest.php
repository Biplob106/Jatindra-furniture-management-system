<?php

use App\Enums\LedgerDirection;
use App\Enums\SupplierLedgerEntryType;
use App\Models\EmployeeLedger;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Services\LedgerService;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->supplier = Supplier::factory()->create();
});

it('takes the direction from the entry type', function (string $type, string $expected) {
    $entry = $this->ledger->recordSupplier(
        supplier: $this->supplier,
        type: SupplierLedgerEntryType::from($type),
        amount: '500.00',
        entryDate: '2026-07-20',
    );

    expect($entry->direction->value)->toBe($expected);
})->with([
    'a purchase is a credit, we owe more' => ['purchase', 'credit'],
    'a payment is a debit, we owe less' => ['payment', 'debit'],
    'a return is a debit' => ['return', 'debit'],
    'a discount is a debit' => ['discount', 'debit'],
]);

/**
 * Positive means we owe them. Inverting this is silently wrong money.
 */
it('refuses to write a fixed type in the wrong direction', function () {
    expect(fn () => $this->ledger->recordSupplier(
        supplier: $this->supplier,
        type: SupplierLedgerEntryType::Purchase,
        amount: '500.00',
        entryDate: '2026-07-20',
        direction: LedgerDirection::Debit,
    ))->toThrow(InvalidArgumentException::class);

    expect(SupplierLedger::count())->toBe(0);
});

it('requires a direction for the two types that go either way', function (string $type) {
    expect(fn () => $this->ledger->recordSupplier(
        supplier: $this->supplier,
        type: SupplierLedgerEntryType::from($type),
        amount: '500.00',
        entryDate: '2026-07-20',
    ))->toThrow(InvalidArgumentException::class);
})->with(['opening', 'adjustment']);

it('writes an opening due in the direction it is told', function () {
    $entry = $this->ledger->recordSupplier(
        supplier: $this->supplier,
        type: SupplierLedgerEntryType::Opening,
        amount: '18000.00',
        entryDate: '2026-07-01',
        direction: LedgerDirection::Credit,
    );

    expect($entry->direction)->toBe(LedgerDirection::Credit)
        ->and($entry->amount)->toBe('18000.00')
        ->and(SupplierLedger::count())->toBe(1);
});

it('refuses a negative amount rather than flipping it', function () {
    expect(fn () => $this->ledger->recordSupplier(
        supplier: $this->supplier,
        type: SupplierLedgerEntryType::Purchase,
        amount: '-500.00',
        entryDate: '2026-07-20',
    ))->toThrow(InvalidArgumentException::class);

    expect(SupplierLedger::count())->toBe(0);
});

it('keeps the reference to the row that caused it', function () {
    $purchase = Purchase::factory()->onCredit('12000.00')->create([
        'supplier_id' => $this->supplier->id,
    ]);

    $entry = $this->ledger->recordSupplier(
        supplier: $this->supplier,
        type: SupplierLedgerEntryType::Purchase,
        amount: '12000.00',
        entryDate: '2026-07-20',
        reference: $purchase,
    );

    expect($entry->reference_type)->toBe(Purchase::class)
        ->and($entry->reference_id)->toBe($purchase->id);
});

it('computes the balance as credits minus debits', function () {
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '12000.00', '2026-07-20');
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '8000.00', '2026-07-21');
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Payment, '5000.00', '2026-07-22');

    expect($this->ledger->supplierBalanceFor($this->supplier))->toBe('15000.00');
});

/**
 * A supplier we have overpaid owes us. The sign says so rather than the
 * balance clamping at zero.
 */
it('goes negative when we have paid more than we owe', function () {
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '5000.00', '2026-07-20');
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Payment, '7000.00', '2026-07-21');

    expect($this->ledger->supplierBalanceFor($this->supplier))->toBe('-2000.00');
});

it('holds to the paisa', function () {
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '1234.57', '2026-07-20');
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Payment, '234.56', '2026-07-21');

    expect($this->ledger->supplierBalanceFor($this->supplier))->toBe('1000.01');
});

it('reads zero for a supplier with no entries', function () {
    expect($this->ledger->supplierBalanceFor($this->supplier))->toBe('0.00');
});

it('keeps two suppliers apart', function () {
    $other = Supplier::factory()->create();

    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '12000.00', '2026-07-20');
    $this->ledger->recordSupplier($other, SupplierLedgerEntryType::Purchase, '3000.00', '2026-07-20');

    expect($this->ledger->supplierBalanceFor($this->supplier))->toBe('12000.00')
        ->and($this->ledger->supplierBalanceFor($other))->toBe('3000.00');
});

it('reads many balances in one query for the payable list', function () {
    $other = Supplier::factory()->create();
    $untouched = Supplier::factory()->create();

    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '12000.00', '2026-07-20');
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Payment, '2000.00', '2026-07-21');
    $this->ledger->recordSupplier($other, SupplierLedgerEntryType::Purchase, '3000.00', '2026-07-20');

    $balances = $this->ledger->supplierBalancesFor([$this->supplier->id, $other->id, $untouched->id]);

    expect($balances[$this->supplier->id])->toBe('10000.00')
        ->and($balances[$other->id])->toBe('3000.00')
        // A supplier with no rows is simply absent, not zero-keyed.
        ->and($balances)->not->toHaveKey($untouched->id);
});

it('reads nothing for an empty list', function () {
    expect($this->ledger->supplierBalancesFor([]))->toBe([]);
});

/**
 * The worker ledger and the supplier ledger share one direction-resolving
 * path. Neither may write into the other's table.
 */
it('writes only to supplier_ledger', function () {
    $this->ledger->recordSupplier($this->supplier, SupplierLedgerEntryType::Purchase, '12000.00', '2026-07-20');

    expect(SupplierLedger::count())->toBe(1)
        ->and(EmployeeLedger::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});
