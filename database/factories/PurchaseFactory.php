<?php

namespace Database\Factories;

use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_no' => 'PO-2607-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'supplier_id' => Supplier::factory(),
            'shop_id' => null,
            'purchase_date' => '2026-07-20',
            'reference_no' => null,
            'payment_type' => PurchasePaymentType::Cash,
            'payment_due_date' => null,
            'subtotal' => 0,
            'transport_cost' => 0,
            'discount' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'status' => PurchaseStatus::Paid,
            'note' => null,
            'created_by' => null,
        ];
    }

    /**
     * A challan taken on credit, still owed in full.
     */
    public function onCredit(string $total, ?string $dueDate = null): static
    {
        return $this->state(fn () => [
            'payment_type' => PurchasePaymentType::Credit,
            'payment_due_date' => $dueDate,
            'subtotal' => $total,
            'total_amount' => $total,
            'paid_amount' => 0,
            'due_amount' => $total,
            'status' => PurchaseStatus::Pending,
        ]);
    }

    public function withTotals(string $total, string $paid = '0.00'): static
    {
        $due = bcsub($total, $paid, 2);

        return $this->state(fn () => [
            'subtotal' => $total,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_amount' => $due,
            'status' => match (true) {
                bccomp($due, '0.00', 2) === 0 => PurchaseStatus::Paid,
                bccomp($paid, '0.00', 2) === 0 => PurchaseStatus::Pending,
                default => PurchaseStatus::Partial,
            },
        ]);
    }
}
