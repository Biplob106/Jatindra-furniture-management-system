<?php

use App\Enums\LedgerEntryType;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Queries\PayrollSummary;
use App\Services\LedgerService;

const FROM = '2026-07-01';
const TO = '2026-07-31';

function ledger(Employee $employee, LedgerEntryType $type, string $amount, string $date): void
{
    app(LedgerService::class)->record($employee, $type, $amount, $date);
}

function payrollRow(Employee $employee, string $from = FROM, string $to = TO): array
{
    $rows = app(PayrollSummary::class)->rows($from, $to);

    return collect($rows)->firstWhere('id', $employee->id);
}

/**
 * The one authoritative calculation: earnings and payments in the period,
 * split so wages, night pay, cash paid and deductions never blur together,
 * plus the all-time outstanding balance.
 */
it('separates earnings, night pay, payments and deductions over the period', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    ledger($employee, LedgerEntryType::WageEarned, '700.00', '2026-07-10');
    ledger($employee, LedgerEntryType::NightAllowance, '350.00', '2026-07-10');
    ledger($employee, LedgerEntryType::Overtime, '200.00', '2026-07-11');
    ledger($employee, LedgerEntryType::Payout, '500.00', '2026-07-12');
    ledger($employee, LedgerEntryType::Fine, '100.00', '2026-07-13');

    $row = payrollRow($employee);

    expect($row['earned'])->toBe('1250.00')    // wage + night + overtime
        ->and($row['night'])->toBe('350.00')
        ->and($row['paid'])->toBe('500.00')     // cash handed over
        ->and($row['deductions'])->toBe('100.00')
        ->and($row['balance'])->toBe('650.00'); // 1250 earned - 500 - 100
});

/**
 * Earnings before the period belong to an earlier payroll, so they stay out of
 * the period figures — but they still count toward the outstanding balance,
 * which is all-time.
 */
it('counts only the period for earned but all time for the balance', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700]);

    ledger($employee, LedgerEntryType::WageEarned, '700.00', '2026-06-30'); // before the window
    ledger($employee, LedgerEntryType::WageEarned, '700.00', '2026-07-10'); // inside

    $row = payrollRow($employee);

    expect($row['earned'])->toBe('700.00')
        ->and($row['balance'])->toBe('1400.00');
});

it('reports zeroes for an active worker with no entries in the period', function () {
    $employee = Employee::factory()->create(['name' => 'ফাঁকা কর্মী']);

    $row = payrollRow($employee);

    expect($row['earned'])->toBe('0.00')
        ->and($row['paid'])->toBe('0.00')
        ->and($row['balance'])->toBe('0.00');
});

it('totals the whole crew to the paisa', function () {
    $a = Employee::factory()->create(['daily_rate' => 333.33]);
    $b = Employee::factory()->create(['daily_rate' => 333.33]);

    ledger($a, LedgerEntryType::WageEarned, '333.33', '2026-07-10');
    ledger($b, LedgerEntryType::WageEarned, '333.33', '2026-07-10');

    $totals = app(PayrollSummary::class)->totals(FROM, TO);

    expect($totals['earned'])->toBe('666.66');
});
