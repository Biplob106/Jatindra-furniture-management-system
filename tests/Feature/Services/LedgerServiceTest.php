<?php

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Services\LedgerService;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->employee = Employee::factory()->create();
});

it('takes the direction from the entry type', function (string $type, string $expected) {
    $entry = $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::from($type),
        amount: '500.00',
        entryDate: '2026-07-20',
    );

    expect($entry->direction->value)->toBe($expected);
})->with([
    'wage_earned is a credit' => ['wage_earned', 'credit'],
    'piece_earned is a credit' => ['piece_earned', 'credit'],
    'overtime is a credit' => ['overtime', 'credit'],
    'bonus is a credit' => ['bonus', 'credit'],
    'advance is a debit' => ['advance', 'debit'],
    'tiffin is a debit' => ['tiffin', 'debit'],
    'payout is a debit' => ['payout', 'debit'],
    'fine is a debit' => ['fine', 'debit'],
]);

/**
 * A sign error in this table is money quietly moving the wrong way, so a
 * caller contradicting a fixed type is treated as a bug, not a preference.
 */
it('refuses to write a fixed type in the wrong direction', function () {
    expect(fn () => $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::WageEarned,
        amount: '500.00',
        entryDate: '2026-07-20',
        direction: LedgerDirection::Debit,
    ))->toThrow(InvalidArgumentException::class);

    expect(EmployeeLedger::count())->toBe(0);
});

it('requires a direction for the two types that go either way', function (string $type) {
    expect(fn () => $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::from($type),
        amount: '500.00',
        entryDate: '2026-07-20',
    ))->toThrow(InvalidArgumentException::class);
})->with(['opening', 'adjustment']);

it('accepts an adjustment in either direction', function () {
    $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::Adjustment,
        amount: '100.00',
        entryDate: '2026-07-20',
        direction: LedgerDirection::Credit,
    );

    $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::Adjustment,
        amount: '40.00',
        entryDate: '2026-07-21',
        direction: LedgerDirection::Debit,
    );

    expect($this->ledger->balanceFor($this->employee))->toBe('60.00');
});

it('rejects a negative amount', function () {
    expect(fn () => $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::Bonus,
        amount: '-100.00',
        entryDate: '2026-07-20',
    ))->toThrow(InvalidArgumentException::class);

    expect(EmployeeLedger::count())->toBe(0);
});

/**
 * Positive means the shop owes the worker. Inverting this is silently wrong
 * money, so it is asserted with earnings and payments both present.
 */
it('computes the balance as credits minus debits', function () {
    $this->ledger->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-01');
    $this->ledger->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-02');
    $this->ledger->record($this->employee, LedgerEntryType::Advance, '500.00', '2026-07-02');
    $this->ledger->record($this->employee, LedgerEntryType::Tiffin, '60.00', '2026-07-02');

    // 1400 earned, 560 taken.
    expect($this->ledger->balanceFor($this->employee))->toBe('840.00');
});

it('goes negative when a worker has taken more than they earned', function () {
    $this->ledger->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-01');
    $this->ledger->record($this->employee, LedgerEntryType::Advance, '2000.00', '2026-07-01');

    expect($this->ledger->balanceFor($this->employee))->toBe('-1300.00');
});

it('reports a zero balance for a worker with no rows', function () {
    expect($this->ledger->balanceFor($this->employee))->toBe('0.00');
});

it('holds precision to the paisa over many small entries', function () {
    foreach (range(1, 100) as $i) {
        $this->ledger->record($this->employee, LedgerEntryType::WageEarned, '0.07', '2026-07-01');
    }

    expect($this->ledger->balanceFor($this->employee))->toBe('7.00');
});

it('loads balances for many employees in one query', function () {
    $second = Employee::factory()->create();
    $third = Employee::factory()->create();

    $this->ledger->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-01');
    $this->ledger->record($second, LedgerEntryType::WageEarned, '900.00', '2026-07-01');
    $this->ledger->record($second, LedgerEntryType::Advance, '200.00', '2026-07-01');

    $balances = $this->ledger->balancesFor([$this->employee->id, $second->id, $third->id]);

    expect($balances[$this->employee->id])->toBe('700.00')
        ->and($balances[$second->id])->toBe('700.00')
        // An employee with no rows is simply absent, not zero.
        ->and($balances)->not->toHaveKey($third->id);
});

it('returns no balances for an empty list', function () {
    expect($this->ledger->balancesFor([]))->toBe([]);
});

it('stores the payment method and note on a payout', function () {
    $entry = $this->ledger->record(
        employee: $this->employee,
        type: LedgerEntryType::Payout,
        amount: '1500.00',
        entryDate: '2026-07-20',
        paymentMethod: PaymentMethod::Bkash,
        note: 'সাপ্তাহিক পরিশোধ',
    );

    expect($entry->payment_method)->toBe(PaymentMethod::Bkash)
        ->and($entry->note)->toBe('সাপ্তাহিক পরিশোধ')
        ->and($entry->direction)->toBe(LedgerDirection::Debit);
});

it('leaves exactly one row when syncing the same reference repeatedly', function () {
    $reference = Attendance::factory()->create(['employee_id' => $this->employee->id]);

    foreach (['700.00', '350.00', '700.00'] as $amount) {
        $this->ledger->syncForReference(
            employee: $this->employee,
            type: LedgerEntryType::WageEarned,
            amount: $amount,
            entryDate: '2026-07-20',
            reference: $reference,
        );
    }

    expect(EmployeeLedger::count())->toBe(1)
        ->and($this->ledger->balanceFor($this->employee))->toBe('700.00');
});

it('leaves no row at all when a sync lands on zero', function () {
    $reference = Attendance::factory()->create(['employee_id' => $this->employee->id]);

    $this->ledger->syncForReference($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-20', $reference);
    $result = $this->ledger->syncForReference($this->employee, LedgerEntryType::WageEarned, '0.00', '2026-07-20', $reference);

    expect($result)->toBeNull()
        ->and(EmployeeLedger::count())->toBe(0);
});

it('keeps syncs for different types on the same reference apart', function () {
    $reference = Attendance::factory()->create(['employee_id' => $this->employee->id]);

    $this->ledger->syncForReference($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-20', $reference);
    $this->ledger->syncForReference($this->employee, LedgerEntryType::Overtime, '150.00', '2026-07-20', $reference);

    expect(EmployeeLedger::count())->toBe(2)
        ->and($this->ledger->balanceFor($this->employee))->toBe('850.00');
});
