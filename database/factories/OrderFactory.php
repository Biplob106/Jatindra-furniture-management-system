<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Drafts carry no number. The confirmed() state issues one.
            'order_no' => null,
            'customer_id' => Customer::factory(),
            'shop_id' => Shop::factory(),
            'order_date' => '2026-07-20',
            'expected_delivery_date' => '2026-08-10',
            'status' => OrderStatus::Draft,
            'subtotal' => 0,
            'discount' => 0,
            'delivery_charge' => 0,
            'total_amount' => 0,
            'paid_amount' => 0,
            'due_amount' => 0,
            'delivery_address' => null,
            'note' => null,
            'created_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Confirmed,
            'order_no' => 'SH-2607-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
        ]);
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
