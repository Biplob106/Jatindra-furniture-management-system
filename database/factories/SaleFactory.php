<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_no' => 'INV-2607-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            // A walk-in with no customer row, which is the common counter sale.
            'customer_id' => null,
            'customer_name' => 'নগদ ক্রেতা',
            'customer_phone' => null,
            'shop_id' => Shop::factory(),
            'sale_date' => '2026-07-20',
            'subtotal' => 0,
            'discount' => 0,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'note' => null,
            'created_by' => null,
        ];
    }

    public function withTotals(string $total, string $paid = '0.00'): static
    {
        return $this->state(fn () => [
            'subtotal' => $total,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_amount' => bcsub($total, $paid, 2),
        ]);
    }
}
