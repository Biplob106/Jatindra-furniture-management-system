<?php

namespace Database\Factories;

use App\Enums\DimensionUnit;
use App\Enums\OrderItemStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = 1;
        $unitPrice = fake()->numberBetween(5000, 60000);

        return [
            'order_id' => Order::factory(),
            'category_id' => null,
            'item_name' => fake()->randomElement(['খাট', 'আলমারি', 'সোফা', 'ড্রেসিং টেবিল']),
            'description' => null,
            'wood_type' => fake()->randomElement(['সেগুন', 'মেহগনি', 'চাম্বল']),
            'design_no' => null,
            'length' => 72,
            'width' => 60,
            'height' => 24,
            'dimension_unit' => DimensionUnit::Inch,
            'polish_type' => null,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $quantity * $unitPrice,
            'target_date' => null,
            'status' => OrderItemStatus::Pending,
            'remarks' => null,
        ];
    }
}
