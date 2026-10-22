<?php

use App\Actions\Payroll\ReversePayrollSettlement;
use App\Actions\Payroll\SettlePayroll;
use App\Enums\LedgerEntryType;
use App\Models\Employee;
use App\Models\PayrollSettlement;
use App\Services\LedgerService;

const S_FROM = '2026-07-01';
const S_TO = '2026-07-31';

beforeEach(function () {
    $this->employee = Employee::factory()->create(['daily_rate' => 700]);

    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-10');
    app(LedgerService::class)->record($this->employee, LedgerEntryType::NightAllowance, '350.00', '2026-07-10');
    app(LedgerService::class)->record($this->employee, LedgerEntryType::Payout, '500.00', '2026-07-12');
    app(LedgerService::class)->record($this->employee, LedgerEntryType::Fine, '100.00', '2026-07-13');
});

/**
 * Finalizing a period freezes each worker's figures into an immutable snapshot.
 * The ledger stays the source of truth for live balances; this is the archived
 * record of what the period was officially settled at.
 */
it('snapshots each worker for the period', function () {
    app(SettlePayroll::class)->handle(S_FROM, S_TO);

    $settlement = PayrollSettlement::where('employee_id', $this->employee->id)->sole();

    expect($settlement->earned)->toBe('1050.00')
        ->and($settlement->night)->toBe('350.00')
        ->and($settlement->paid)->toBe('500.00')
        ->and($settlement->deductions)->toBe('100.00')
        ->and($settlement->net_balance)->toBe('450.00')
        ->and($settlement->period_start->toDateString())->toBe(S_FROM)
        ->and($settlement->period_end->toDateString())->toBe(S_TO);
});

/**
 * Finalizing the same period twice must not stack a second snapshot. A period
 * is finalized once; correcting it is a reversal, not a re-run.
 */
it('is idempotent for a period', function () {
    app(SettlePayroll::class)->handle(S_FROM, S_TO);
    $first = PayrollSettlement::count();

    $result = app(SettlePayroll::class)->handle(S_FROM, S_TO);

    expect(PayrollSettlement::count())->toBe($first)
        ->and($result['skipped'])->toBe(1)
        ->and($result['settled'])->toBe(0);
});

/**
 * A later ledger correction must not change an already-finalized snapshot.
 */
it('does not move a snapshot when the ledger changes afterward', function () {
    app(SettlePayroll::class)->handle(S_FROM, S_TO);

    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '999.00', '2026-07-20');

    expect(PayrollSettlement::sole()->earned)->toBe('1050.00');
});

/**
 * Reversing leaves the row for the record but frees the period to be finalized
 * again with corrected figures.
 */
it('lets a reversed period be finalized again', function () {
    app(SettlePayroll::class)->handle(S_FROM, S_TO);
    $settlement = PayrollSettlement::sole();

    app(ReversePayrollSettlement::class)->handle($settlement);

    expect($settlement->fresh()->reversed_at)->not->toBeNull();

    $result = app(SettlePayroll::class)->handle(S_FROM, S_TO);

    expect($result['settled'])->toBe(1)
        ->and(PayrollSettlement::whereNull('reversed_at')->count())->toBe(1)
        ->and(PayrollSettlement::count())->toBe(2);
});
