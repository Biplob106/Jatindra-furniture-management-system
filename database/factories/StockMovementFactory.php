<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shop_id' => null,
            'movement_date' => '2026-07-20',
            'type' => StockMovementType::ProductionIn,
            'quantity' => '1.00',
            'unit_cost' => '18000.00',
            'reference_type' => null,
            'reference_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }

    public function sold(string $quantity = '1.00'): static
    {
        return $this->state(fn () => [
            'type' => StockMovementType::SaleOut,
            'quantity' => $quantity,
        ]);
    }
}
