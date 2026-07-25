<?php

namespace App\Actions\Employees;

use App\Enums\CashPaymentMethod;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Services\CashService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hands money to a worker, or adjusts what they are owed.
 *
 * This is the first operation that writes both ledgers, and section 9 of
 * docs/schema.md is explicit about which:
 *
 *   advance / tiffin / payout  ->  employee_ledger debit + transactions out
 *   fine                       ->  employee_ledger debit, no cash moves
 *   bonus                      ->  employee_ledger credit, no cash moves
 *
 * A fine does not move money, it reduces what the shop owes. A bonus is the
 * same in reverse. Writing a transactions row for either would put money in
 * the daily closing that never left the drawer.
 */
class RecordEmployeePayment
{
    /** Types that hand over physical money and therefore need an account. */
    private const MOVES_CASH = [
        LedgerEntryType::Advance,
        LedgerEntryType::Tiffin,
        LedgerEntryType::Payout,
    ];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly CashService $cash,
    ) {}

    public function handle(
        Employee $employee,
        LedgerEntryType $type,
        string $amount,
        string $entryDate,
        ?Account $account = null,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?string $note = null,
        ?int $createdBy = null,
    ): EmployeeLedger {
        $movesCash = in_array($type, self::MOVES_CASH, true);

        if ($movesCash && $account === null) {
            throw new InvalidArgumentException("{$type->value} hands over money, so it needs an account to take it from.");
        }

        if (! $movesCash && ! in_array($type, [LedgerEntryType::Fine, LedgerEntryType::Bonus], true)) {
            throw new InvalidArgumentException("{$type->value} is not an employee payment.");
        }

        return DB::transaction(function () use ($employee, $type, $amount, $entryDate, $account, $paymentMethod, $note, $createdBy, $movesCash) {
            $entry = $this->ledger->record(
                employee: $employee,
                type: $type,
                amount: $amount,
                entryDate: $entryDate,
                paymentMethod: $movesCash ? $paymentMethod : null,
                note: $note,
                createdBy: $createdBy,
            );

            if ($movesCash) {
                // withdraw, not record, so a cash box cannot be overdrawn.
                // It throws, and this whole transaction rolls back with it,
                // leaving no ledger row behind either.
                $this->cash->withdraw(
                    account: $account,
                    amount: $amount,
                    txnDate: $entryDate,
                    source: TransactionSource::EmployeePayment,
                    party: $employee,
                    sourceId: $entry->id,
                    paymentMethod: CashPaymentMethod::from($paymentMethod->value),
                    note: $note ?? $type->label().' — '.$employee->name,
                    createdBy: $createdBy,
                    shopId: $employee->shop_id,
                );
            }

            return $entry;
        });
    }
}
