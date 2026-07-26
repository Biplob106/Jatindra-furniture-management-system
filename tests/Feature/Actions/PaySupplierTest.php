<?php

use App\Actions\Purchases\PaySupplier;
use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\LedgerDirection;
use App\Enums\PartyType;
use App\Enums\PurchaseStatus;
use App\Enums\SupplierLedgerEntryType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\PartyPayment;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;

beforeEach(function () {
    $this->action = app(PaySupplier::class);
    $this->ledger = app(LedgerService::class);

    $this->shop = Shop::factory()->create();
    $this->supplier = Supplier::factory()->create();

    $this->drawer = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 100000,
        'current_balance' => 100000,
    ]);
});

/**
 * A challan already on the books, taken on credit and owed in full.
 */
function challan(string $total, string $date = '2026-07-10'): Purchase
{
    $purchase = Purchase::factory()->onCredit($total)->create([
        'supplier_id' => test()->supplier->id,
        'shop_id' => test()->shop->id,
        'purchase_date' => $date,
    ]);

    test()->ledger->recordSupplier(
        supplier: test()->supplier,
        type: SupplierLedgerEntryType::Purchase,
        amount: $total,
        entryDate: $date,
        reference: $purchase,
    );

    return $purchase;
}

/**
 * @param  array<int, string>|null  $allocations
 */
function pay(string $amount, ?array $allocations = null, array $overrides = []): PartyPayment
{
    return test()->action->handle(
        supplier: test()->supplier,
        account: test()->drawer,
        data: array_merge([
            'amount' => $amount,
            'payment_date' => '2026-07-20',
        ], $overrides),
        allocations: $allocations,
    );
}

it('writes the payment, the allocation, the ledger debit and the money out', function () {
    $purchase = challan('12000.00');

    $payment = pay('12000');

    expect(PartyPayment::count())->toBe(1)
        ->and(PaymentAllocation::count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        // The purchase credit from the challan, plus this payment's debit.
        ->and(SupplierLedger::count())->toBe(2)
        ->and($payment->amount)->toBe('12000.00')
        ->and($payment->party_type)->toBe(PartyType::Supplier)
        ->and($payment->direction)->toBe(TransactionDirection::Out)
        ->and($purchase->refresh()->due_amount)->toBe('0.00')
        ->and($purchase->status)->toBe(PurchaseStatus::Paid);
});

it('clears what we owe', function () {
    challan('12000.00');

    pay('12000');

    $debit = SupplierLedger::debits()->sole();

    expect($debit->type)->toBe(SupplierLedgerEntryType::Payment)
        ->and($debit->direction)->toBe(LedgerDirection::Debit)
        ->and($debit->amount)->toBe('12000.00')
        ->and($debit->reference_type)->toBe(PartyPayment::class)
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('0.00');
});

it('takes the money out of the drawer', function () {
    challan('12000.00');

    $payment = pay('12000');

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(TransactionDirection::Out)
        ->and($transaction->amount)->toBe('12000.00')
        ->and($transaction->source_type)->toBe(TransactionSource::PurchasePayment)
        ->and($transaction->source_id)->toBe($payment->id)
        ->and($transaction->party_type)->toBe(Supplier::class)
        ->and($transaction->party_id)->toBe($this->supplier->id)
        ->and($this->drawer->refresh()->current_balance)->toBe('88000.00');
});

/**
 * How a supplier expects their book to be cleared, and how the aging list is
 * read: the oldest challan first.
 */
it('spreads a payment over the oldest challans first', function () {
    $july = challan('10000.00', '2026-07-01');
    $august = challan('8000.00', '2026-07-15');

    pay('12000');

    expect($july->refresh()->due_amount)->toBe('0.00')
        ->and($july->status)->toBe(PurchaseStatus::Paid)
        ->and($august->refresh()->due_amount)->toBe('6000.00')
        ->and($august->status)->toBe(PurchaseStatus::Partial)
        ->and(PaymentAllocation::count())->toBe(2);
});

it('stops allocating once the money runs out', function () {
    $first = challan('10000.00', '2026-07-01');
    $untouched = challan('8000.00', '2026-07-15');

    pay('4000');

    expect($first->refresh()->due_amount)->toBe('6000.00')
        ->and($untouched->refresh()->due_amount)->toBe('8000.00')
        ->and($untouched->status)->toBe(PurchaseStatus::Pending)
        ->and(PaymentAllocation::count())->toBe(1);
});

it('settles the challans the caller picked', function () {
    $older = challan('10000.00', '2026-07-01');
    $newer = challan('8000.00', '2026-07-15');

    pay('8000', [$newer->id => '8000']);

    expect($newer->refresh()->due_amount)->toBe('0.00')
        ->and($newer->status)->toBe(PurchaseStatus::Paid)
        ->and($older->refresh()->due_amount)->toBe('10000.00')
        ->and(PaymentAllocation::sole()->allocatable_id)->toBe($newer->id);
});

it('splits one payment across several picked challans', function () {
    $first = challan('10000.00', '2026-07-01');
    $second = challan('8000.00', '2026-07-15');

    pay('9000', [$first->id => '5000', $second->id => '4000']);

    expect($first->refresh()->due_amount)->toBe('5000.00')
        ->and($second->refresh()->due_amount)->toBe('4000.00')
        ->and(PaymentAllocation::count())->toBe(2)
        ->and(Transaction::sole()->amount)->toBe('9000.00');
});

/**
 * A shop that pays more than the challans on the books has still paid it.
 * Clamping the ledger debit to what was allocated would lose real money.
 */
it('debits the whole amount even when part of it sits on account', function () {
    $purchase = challan('10000.00');

    pay('15000');

    expect(SupplierLedger::debits()->sole()->amount)->toBe('15000.00')
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('-5000.00')
        ->and(PaymentAllocation::sum('allocated_amount'))->toEqual('10000.00')
        ->and($purchase->refresh()->status)->toBe(PurchaseStatus::Paid)
        ->and($this->drawer->refresh()->current_balance)->toBe('85000.00');
});

it('pays a supplier with nothing on the books at all', function () {
    $payment = pay('5000');

    expect($payment->amount)->toBe('5000.00')
        ->and(PaymentAllocation::count())->toBe(0)
        ->and(SupplierLedger::count())->toBe(1)
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('-5000.00')
        ->and(Transaction::count())->toBe(1);
});

/**
 * Overpaying one challan to make the arithmetic fit would leave a negative due
 * and a purchase that reads as more than paid.
 */
it('refuses to allocate more than a challan owes', function () {
    $purchase = challan('10000.00');

    expect(fn () => pay('15000', [$purchase->id => '15000']))
        ->toThrow(InvalidArgumentException::class);

    expect(PartyPayment::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($purchase->refresh()->due_amount)->toBe('10000.00');
});

it('refuses allocations that come to more than was handed over', function () {
    $first = challan('10000.00', '2026-07-01');
    $second = challan('8000.00', '2026-07-15');

    expect(fn () => pay('5000', [$first->id => '4000', $second->id => '4000']))
        ->toThrow(InvalidArgumentException::class);

    expect(PartyPayment::count())->toBe(0)
        ->and(PaymentAllocation::count())->toBe(0);
});

it('refuses to settle another supplier the challan does not belong to', function () {
    $other = Supplier::factory()->create();
    $theirs = Purchase::factory()->onCredit('10000.00')->create([
        'supplier_id' => $other->id,
        'purchase_date' => '2026-07-01',
    ]);

    expect(fn () => pay('10000', [$theirs->id => '10000']))
        ->toThrow(InvalidArgumentException::class);

    expect(PartyPayment::count())->toBe(0)
        ->and($theirs->refresh()->due_amount)->toBe('10000.00');
});

it('refuses to allocate against a challan already paid off', function () {
    $settled = Purchase::factory()->withTotals('10000.00', '10000.00')->create([
        'supplier_id' => $this->supplier->id,
        'purchase_date' => '2026-07-01',
    ]);

    expect(fn () => pay('5000', [$settled->id => '5000']))
        ->toThrow(InvalidArgumentException::class);

    expect(PartyPayment::count())->toBe(0);
});

it('refuses an allocation of nothing', function () {
    $purchase = challan('10000.00');

    expect(fn () => pay('5000', [$purchase->id => '0']))
        ->toThrow(InvalidArgumentException::class);

    expect(PartyPayment::count())->toBe(0);
});

it('refuses to hand over nothing', function (string $amount) {
    challan('10000.00');

    expect(fn () => pay($amount))->toThrow(InvalidArgumentException::class);

    expect(PartyPayment::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
})->with(['0', '-500']);

/**
 * A refusal takes the payment, its allocations and the ledger debit with it.
 */
it('persists nothing when the drawer cannot cover the payment', function () {
    $thin = Account::factory()->create([
        'type' => AccountType::Cash,
        'shop_id' => $this->shop->id,
        'opening_balance' => 500,
        'current_balance' => 500,
    ]);

    $purchase = challan('10000.00');

    expect(fn () => $this->action->handle(
        supplier: $this->supplier,
        account: $thin,
        data: ['amount' => '10000', 'payment_date' => '2026-07-20'],
    ))->toThrow(RuntimeException::class);

    expect(PartyPayment::count())->toBe(0)
        ->and(PaymentAllocation::count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        // Only the challan's own credit survives.
        ->and(SupplierLedger::count())->toBe(1)
        ->and($purchase->refresh()->due_amount)->toBe('10000.00')
        ->and($purchase->status)->toBe(PurchaseStatus::Pending)
        ->and($thin->refresh()->current_balance)->toBe('500.00');
});

it('records the method the money left by and who recorded it', function () {
    $user = User::factory()->create();
    challan('10000.00');

    $payment = $this->action->handle(
        supplier: $this->supplier,
        account: $this->drawer,
        data: [
            'amount' => '10000',
            'payment_date' => '2026-07-20',
            'payment_method' => CashPaymentMethod::Cheque,
            'reference_no' => 'CHQ-4471',
            'note' => 'জুলাই মাসের বিল',
        ],
        userId: $user->id,
    );

    expect($payment->payment_method)->toBe(CashPaymentMethod::Cheque)
        ->and($payment->reference_no)->toBe('CHQ-4471')
        ->and($payment->created_by)->toBe($user->id)
        ->and(Transaction::sole()->payment_method)->toBe(CashPaymentMethod::Cheque)
        ->and(SupplierLedger::debits()->sole()->created_by)->toBe($user->id);
});

it('holds to the paisa across several challans', function () {
    $first = challan('1234.57', '2026-07-01');
    $second = challan('2345.68', '2026-07-02');

    pay('3580.24');

    expect($first->refresh()->due_amount)->toBe('0.00')
        ->and($second->refresh()->due_amount)->toBe('0.01')
        ->and($second->status)->toBe(PurchaseStatus::Partial)
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('0.01');
});

it('leaves a part-paid challan payable for the rest', function () {
    $purchase = challan('10000.00');

    pay('4000');
    pay('6000');

    expect($purchase->refresh()->paid_amount)->toBe('10000.00')
        ->and($purchase->due_amount)->toBe('0.00')
        ->and($purchase->status)->toBe(PurchaseStatus::Paid)
        ->and(PartyPayment::count())->toBe(2)
        ->and(PaymentAllocation::count())->toBe(2)
        ->and($this->ledger->supplierBalanceFor($this->supplier))->toBe('0.00');
});
