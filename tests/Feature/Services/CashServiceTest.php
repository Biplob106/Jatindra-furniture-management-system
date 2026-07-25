<?php

use App\Enums\AccountType;
use App\Enums\CashPaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\Transaction;
use App\Services\CashService;

beforeEach(function () {
    $this->cash = app(CashService::class);
    $this->account = Account::factory()->create(['opening_balance' => 1000, 'current_balance' => 1000]);
});

it('writes one transaction row and moves the balance with it', function () {
    $this->cash->record(
        account: $this->account,
        direction: TransactionDirection::In,
        amount: '500.00',
        txnDate: '2026-07-20',
        source: TransactionSource::Sale,
    );

    expect(Transaction::count())->toBe(1)
        ->and($this->account->fresh()->current_balance)->toBe('1500.00');
});

it('lowers the balance on the way out', function () {
    $this->cash->record($this->account, TransactionDirection::Out, '250.50', '2026-07-20', TransactionSource::Expense);

    expect($this->account->fresh()->current_balance)->toBe('749.50');
});

it('rejects a zero or negative amount', function (string $amount) {
    expect(fn () => $this->cash->record($this->account, TransactionDirection::In, $amount, '2026-07-20', TransactionSource::Sale))
        ->toThrow(InvalidArgumentException::class);

    expect(Transaction::count())->toBe(0)
        ->and($this->account->fresh()->current_balance)->toBe('1000.00');
})->with(['0.00', '-100.00']);

it('refuses an amount that is not a plain decimal', function () {
    expect(fn () => $this->cash->record($this->account, TransactionDirection::In, '100.00; DROP TABLE accounts', '2026-07-20', TransactionSource::Sale))
        ->toThrow(InvalidArgumentException::class);

    expect(Transaction::count())->toBe(0);
});

/**
 * The stored running balance is the one exception to the no-running-balance
 * rule, so it has to agree with the rows it summarises. If these ever drift,
 * the cash box and the books disagree.
 */
it('keeps current_balance equal to the sum of its rows', function () {
    foreach (['100.25', '340.10', '9.99', '1250.00'] as $amount) {
        $this->cash->record($this->account, TransactionDirection::In, $amount, '2026-07-20', TransactionSource::Sale);
    }

    foreach (['75.50', '300.00'] as $amount) {
        $this->cash->record($this->account, TransactionDirection::Out, $amount, '2026-07-20', TransactionSource::Expense);
    }

    $account = $this->account->fresh();

    // 1000 + 1700.34 - 375.50
    expect($account->current_balance)->toBe('2324.84')
        ->and($this->cash->computedBalanceFor($account))->toBe('2324.84');
});

it('holds to the paisa over a hundred small movements', function () {
    foreach (range(1, 100) as $i) {
        $this->cash->record($this->account, TransactionDirection::In, '0.07', '2026-07-20', TransactionSource::Sale);
    }

    expect($this->account->fresh()->current_balance)->toBe('1007.00');
});

it('stores the source, party and payment method', function () {
    $employee = Employee::factory()->create();

    $transaction = $this->cash->record(
        account: $this->account,
        direction: TransactionDirection::Out,
        amount: '1500.00',
        txnDate: '2026-07-20',
        source: TransactionSource::EmployeePayment,
        party: $employee,
        paymentMethod: CashPaymentMethod::Bkash,
        note: 'সাপ্তাহিক পরিশোধ',
    );

    expect($transaction->source_type)->toBe(TransactionSource::EmployeePayment)
        ->and($transaction->party_type)->toBe(Employee::class)
        ->and($transaction->party_id)->toBe($employee->id)
        ->and($transaction->payment_method)->toBe(CashPaymentMethod::Bkash)
        ->and($transaction->note)->toBe('সাপ্তাহিক পরিশোধ');
});

it('inherits the shop from the account when none is given', function () {
    $shop = Shop::factory()->create();
    $account = Account::factory()->create(['shop_id' => $shop->id]);

    $transaction = $this->cash->record($account, TransactionDirection::In, '100.00', '2026-07-20', TransactionSource::Sale);

    expect($transaction->shop_id)->toBe($shop->id);
});

/**
 * A drawer cannot hold less than nothing.
 */
it('refuses to overdraw a cash box', function () {
    expect(fn () => $this->cash->withdraw($this->account, '1500.00', '2026-07-20', TransactionSource::EmployeePayment))
        ->toThrow(RuntimeException::class);

    expect(Transaction::count())->toBe(0)
        ->and($this->account->fresh()->current_balance)->toBe('1000.00');
});

it('allows withdrawing exactly what a cash box holds', function () {
    $this->cash->withdraw($this->account, '1000.00', '2026-07-20', TransactionSource::EmployeePayment);

    expect($this->account->fresh()->current_balance)->toBe('0.00');
});

/**
 * An overdraft is a real thing at a bank, so only cash is capped.
 */
it('lets a bank account go negative', function () {
    $bank = Account::factory()->create([
        'type' => AccountType::Bank,
        'opening_balance' => 100,
        'current_balance' => 100,
    ]);

    $this->cash->withdraw($bank, '500.00', '2026-07-20', TransactionSource::PurchasePayment);

    expect($bank->fresh()->current_balance)->toBe('-400.00');
});

it('reports the computed balance from the opening figure when there are no rows', function () {
    expect($this->cash->computedBalanceFor($this->account))->toBe('1000.00');
});
