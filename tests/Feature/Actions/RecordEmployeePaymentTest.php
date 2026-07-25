<?php

use App\Actions\Employees\RecordEmployeePayment;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Transaction;
use App\Services\CashService;
use App\Services\LedgerService;

beforeEach(function () {
    $this->action = app(RecordEmployeePayment::class);
    $this->employee = Employee::factory()->create();
    $this->account = Account::factory()->create(['opening_balance' => 10000, 'current_balance' => 10000]);

    // The worker has earned something to be paid out of.
    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '5000.00', '2026-07-01');
});

/**
 * Section 9: handing money over writes a ledger debit AND a cash row. Exactly
 * one of each, never two of either.
 */
it('writes one ledger debit and one cash row for a payout', function () {
    $this->action->handle(
        employee: $this->employee,
        type: LedgerEntryType::Payout,
        amount: '2000.00',
        entryDate: '2026-07-20',
        account: $this->account,
    );

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->count())->toBe(1)
        ->and(Transaction::count())->toBe(1);

    $transaction = Transaction::sole();

    expect($transaction->direction)->toBe(TransactionDirection::Out)
        ->and($transaction->amount)->toBe('2000.00')
        ->and($transaction->source_type)->toBe(TransactionSource::EmployeePayment)
        ->and($transaction->party_type)->toBe(Employee::class)
        ->and($transaction->party_id)->toBe($this->employee->id);
});

it('lowers what the shop owes and what the drawer holds together', function () {
    $this->action->handle($this->employee, LedgerEntryType::Payout, '2000.00', '2026-07-20', $this->account);

    expect(app(LedgerService::class)->balanceFor($this->employee))->toBe('3000.00')
        ->and($this->account->fresh()->current_balance)->toBe('8000.00');
});

it('treats an advance and a tiffin the same way', function (string $type) {
    $this->action->handle($this->employee, LedgerEntryType::from($type), '300.00', '2026-07-20', $this->account);

    expect(EmployeeLedger::where('type', $type)->sole()->direction)->toBe(LedgerDirection::Debit)
        ->and(Transaction::count())->toBe(1)
        ->and($this->account->fresh()->current_balance)->toBe('9700.00');
})->with(['advance', 'tiffin']);

/**
 * A fine is not money leaving the drawer, it is the shop owing less. A cash
 * row here would put money in the daily closing that never moved.
 */
it('writes no cash row for a fine', function () {
    $this->action->handle($this->employee, LedgerEntryType::Fine, '250.00', '2026-07-20');

    expect(EmployeeLedger::where('type', LedgerEntryType::Fine)->sole()->direction)->toBe(LedgerDirection::Debit)
        ->and(Transaction::count())->toBe(0)
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('4750.00')
        ->and($this->account->fresh()->current_balance)->toBe('10000.00');
});

it('writes no cash row for a bonus', function () {
    $this->action->handle($this->employee, LedgerEntryType::Bonus, '1000.00', '2026-07-20');

    expect(EmployeeLedger::where('type', LedgerEntryType::Bonus)->sole()->direction)->toBe(LedgerDirection::Credit)
        ->and(Transaction::count())->toBe(0)
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('6000.00');
});

it('demands an account for anything that hands money over', function (string $type) {
    expect(fn () => $this->action->handle($this->employee, LedgerEntryType::from($type), '500.00', '2026-07-20'))
        ->toThrow(InvalidArgumentException::class);

    expect(Transaction::count())->toBe(0)
        ->and(EmployeeLedger::where('type', $type)->count())->toBe(0);
})->with(['advance', 'tiffin', 'payout']);

it('refuses a type that is not an employee payment', function () {
    expect(fn () => $this->action->handle($this->employee, LedgerEntryType::WageEarned, '500.00', '2026-07-20', $this->account))
        ->toThrow(InvalidArgumentException::class);
});

/**
 * Definition of done, clause 4. The cash box refuses to overdraw, and the
 * ledger row written moments earlier must go with it.
 */
it('leaves no ledger row behind when the drawer cannot cover the payment', function () {
    $small = Account::factory()->create(['opening_balance' => 100, 'current_balance' => 100]);

    expect(fn () => $this->action->handle($this->employee, LedgerEntryType::Payout, '2000.00', '2026-07-20', $small))
        ->toThrow(RuntimeException::class);

    expect(EmployeeLedger::where('type', LedgerEntryType::Payout)->count())->toBe(0)
        ->and(Transaction::count())->toBe(0)
        ->and($small->fresh()->current_balance)->toBe('100.00')
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('5000.00');
});

it('lets a worker be paid more than they have earned, going negative', function () {
    $this->action->handle($this->employee, LedgerEntryType::Advance, '8000.00', '2026-07-20', $this->account);

    // The shop has now over-advanced; the worker owes it back.
    expect(app(LedgerService::class)->balanceFor($this->employee))->toBe('-3000.00');
});

it('records the payment method on both rows', function () {
    $entry = $this->action->handle(
        employee: $this->employee,
        type: LedgerEntryType::Payout,
        amount: '1000.00',
        entryDate: '2026-07-20',
        account: $this->account,
        paymentMethod: PaymentMethod::Nagad,
    );

    expect($entry->payment_method)->toBe(PaymentMethod::Nagad)
        ->and(Transaction::sole()->payment_method->value)->toBe('nagad');
});

it('links the cash row back to the ledger row it paid', function () {
    $entry = $this->action->handle($this->employee, LedgerEntryType::Payout, '1000.00', '2026-07-20', $this->account);

    expect(Transaction::sole()->source_id)->toBe($entry->id);
});

it('keeps the account balance in step with its own rows', function () {
    $this->action->handle($this->employee, LedgerEntryType::Payout, '1234.56', '2026-07-20', $this->account);
    $this->action->handle($this->employee, LedgerEntryType::Advance, '765.44', '2026-07-21', $this->account);

    $account = $this->account->fresh();

    expect($account->current_balance)->toBe('8000.00')
        ->and(app(CashService::class)->computedBalanceFor($account))->toBe('8000.00');
});
