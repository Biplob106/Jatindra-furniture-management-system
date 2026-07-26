<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\SupplierLedgerEntryType;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierLedger>
 */
class SupplierLedgerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'entry_date' => '2026-07-20',
            'type' => SupplierLedgerEntryType::Purchase,
            'direction' => LedgerDirection::Credit,
            'amount' => '5000.00',
            'reference_type' => null,
            'reference_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }

    /** What we owe them more of. */
    public function credit(string $amount): static
    {
        return $this->state(fn () => [
            'direction' => LedgerDirection::Credit,
            'amount' => $amount,
        ]);
    }

    /** What we have paid down. */
    public function debit(string $amount): static
    {
        return $this->state(fn () => [
            'type' => SupplierLedgerEntryType::Payment,
            'direction' => LedgerDirection::Debit,
            'amount' => $amount,
        ]);
    }
}
