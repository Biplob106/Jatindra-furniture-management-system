<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerEntryType;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeLedger>
 */
class EmployeeLedgerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'entry_date' => '2026-07-01',
            'type' => LedgerEntryType::WageEarned,
            'direction' => LedgerDirection::Credit,
            'amount' => 700,
            'reference_type' => null,
            'reference_id' => null,
            'payment_method' => null,
            'note' => null,
            'created_by' => null,
        ];
    }

    public function debit(LedgerEntryType $type = LedgerEntryType::Advance): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'direction' => LedgerDirection::Debit,
        ]);
    }
}
