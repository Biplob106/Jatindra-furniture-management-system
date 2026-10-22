<?php

use App\Actions\Accounts\DepositToAccount;
use App\Actions\Employees\RecordEmployeePayment;
use App\Enums\LedgerEntryType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Employee;
use App\Models\Transaction;

/**
 * Putting the shop's own cash into a box: one cash-in transaction that raises
 * the balance, tagged capital_in so it reads as money the owner added, not a
 * sale or an order payment.
 */
it('raises the account balance with one capital-in transaction', function () {
    $account = Account::factory()->create(['type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0]);

    app(DepositToAccount::class)->handle($account, '2000.00', '2026-10-22');

    $txn = Transaction::sole();

    expect(Transaction::count())->toBe(1)
        ->and($txn->direction)->toBe(TransactionDirection::In)
        ->and($txn->amount)->toBe('2000.00')
        ->and($txn->source_type)->toBe(TransactionSource::CapitalIn)
        ->and($account->fresh()->current_balance)->toBe('2000.00');
});

/**
 * The point of it: once there is money in the box, an advance that was blocked
 * at zero goes through.
 */
it('unblocks an advance that was impossible at zero balance', function () {
    $account = Account::factory()->create(['type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0]);
    $employee = Employee::factory()->create();

    // Empty box: the advance cannot be paid.
    expect(fn () => app(RecordEmployeePayment::class)->handle(
        employee: $employee,
        type: LedgerEntryType::Advance,
        amount: '500.00',
        entryDate: '2026-10-22',
        account: $account,
    ))->toThrow(RuntimeException::class);

    app(DepositToAccount::class)->handle($account, '2000.00', '2026-10-22');

    app(RecordEmployeePayment::class)->handle(
        employee: $employee,
        type: LedgerEntryType::Advance,
        amount: '500.00',
        entryDate: '2026-10-22',
        account: $account->fresh(),
    );

    expect($account->fresh()->current_balance)->toBe('1500.00');
});
