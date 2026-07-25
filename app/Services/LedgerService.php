<?php

namespace App\Services;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only thing that writes employee_ledger.
 *
 * Direction comes from the entry type rather than the caller, so a wage cannot
 * be recorded as a debit by accident. `opening` and `adjustment` are the two
 * types that genuinely go either way and must be told which.
 *
 * Balances are always computed as SUM(credit) - SUM(debit) in SQL. There is no
 * running balance column and there must never be one.
 */
class LedgerService
{
    /**
     * Records one employee ledger row.
     *
     * @param  string  $amount  Money as a string. Never a float.
     */
    public function record(
        Employee $employee,
        LedgerEntryType $type,
        string $amount,
        string $entryDate,
        ?LedgerDirection $direction = null,
        ?Model $reference = null,
        ?PaymentMethod $paymentMethod = null,
        ?string $note = null,
        ?int $createdBy = null,
    ): EmployeeLedger {
        $direction = $this->resolveDirection($type, $direction);

        if (bccomp($amount, '0.00', 2) < 0) {
            throw new InvalidArgumentException('Ledger amounts are never negative. Use the direction instead.');
        }

        return EmployeeLedger::create([
            'employee_id' => $employee->id,
            'entry_date' => $entryDate,
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'payment_method' => $paymentMethod,
            'note' => $note,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Replaces the single ledger row tied to one reference, leaving exactly one
     * row behind, or none when the amount is zero.
     *
     * This is what keeps re-saving a date idempotent: the attendance row owns
     * its wage entry, so saving the same day twice reconciles rather than
     * stacking a second credit.
     */
    public function syncForReference(
        Employee $employee,
        LedgerEntryType $type,
        string $amount,
        string $entryDate,
        Model $reference,
        ?LedgerDirection $direction = null,
        ?string $note = null,
        ?int $createdBy = null,
    ): ?EmployeeLedger {
        return DB::transaction(function () use ($employee, $type, $amount, $entryDate, $reference, $direction, $note, $createdBy) {
            EmployeeLedger::query()
                ->where('reference_type', $reference::class)
                ->where('reference_id', $reference->getKey())
                ->where('type', $type)
                ->delete();

            if (bccomp($amount, '0.00', 2) === 0) {
                return null;
            }

            return $this->record(
                employee: $employee,
                type: $type,
                amount: $amount,
                entryDate: $entryDate,
                direction: $direction,
                reference: $reference,
                note: $note,
                createdBy: $createdBy,
            );
        });
    }

    /**
     * SUM(credit) - SUM(debit) as a string. Positive means the shop owes the
     * worker. Computed in SQL, never accumulated in PHP.
     */
    public function balanceFor(Employee $employee): string
    {
        $balance = EmployeeLedger::query()
            ->where('employee_id', $employee->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS balance")
            ->value('balance');

        return number_format((float) $balance, 2, '.', '');
    }

    /**
     * Balances for many employees at once, keyed by employee id, for the list
     * screen. Avoids a query per row.
     *
     * @param  list<int>  $employeeIds
     * @return array<int, string>
     */
    public function balancesFor(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        return EmployeeLedger::query()
            ->whereIn('employee_id', $employeeIds)
            ->groupBy('employee_id')
            ->selectRaw("employee_id, COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE -amount END), 0) AS balance")
            ->pluck('balance', 'employee_id')
            ->map(fn ($balance) => number_format((float) $balance, 2, '.', ''))
            ->all();
    }

    private function resolveDirection(LedgerEntryType $type, ?LedgerDirection $given): LedgerDirection
    {
        $natural = $type->direction();

        if ($natural !== null) {
            // A caller passing the wrong direction for a fixed type is a bug,
            // not a preference. Fail rather than write money the wrong way.
            if ($given !== null && $given !== $natural) {
                throw new InvalidArgumentException(
                    "{$type->value} is always a {$natural->value}, but {$given->value} was given."
                );
            }

            return $natural;
        }

        if ($given === null) {
            throw new InvalidArgumentException("{$type->value} can go either way, so a direction is required.");
        }

        return $given;
    }
}
