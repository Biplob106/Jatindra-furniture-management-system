<?php

use App\Actions\Employees\RecordEmployeePayment;
use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\DeductionCategory;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Models\Transaction;
use App\Services\LedgerService;

beforeEach(function () {
    $this->action = app(RecordEmployeePayment::class);
    $this->employee = Employee::factory()->create();
    $this->category = DeductionCategory::factory()->create(['name' => 'ভাঙা মালের ক্ষতি']);

    // The worker has earned something for the deduction to bite into.
    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '5000.00', '2026-07-01');
});

/**
 * A custom deduction is the shop owing less, like a fine, but tagged with an
 * admin-defined category. It writes one ledger debit and no cash row.
 */
it('writes one ledger debit and no cash row for a deduction', function () {
    $entry = $this->action->handle(
        employee: $this->employee,
        type: LedgerEntryType::Deduction,
        amount: '500.00',
        entryDate: '2026-07-20',
        deductionCategory: $this->category,
    );

    expect(EmployeeLedger::where('type', LedgerEntryType::Deduction)->count())->toBe(1)
        ->and(Transaction::count())->toBe(0)
        ->and($entry->direction)->toBe(LedgerDirection::Debit)
        ->and($entry->deduction_category_id)->toBe($this->category->id)
        ->and(app(LedgerService::class)->balanceFor($this->employee))->toBe('4500.00');
});

/**
 * A deduction without a category would be an untraceable debit. The whole
 * point of the feature is the category, so it is required.
 */
it('refuses a deduction with no category', function () {
    expect(fn () => $this->action->handle(
        employee: $this->employee,
        type: LedgerEntryType::Deduction,
        amount: '500.00',
        entryDate: '2026-07-20',
    ))->toThrow(InvalidArgumentException::class);

    expect(EmployeeLedger::where('type', LedgerEntryType::Deduction)->count())->toBe(0);
});
