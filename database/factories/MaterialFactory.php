<?php

namespace Database\Factories;

use App\Enums\MaterialCategory;
use App\Enums\MaterialUnit;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'সেগুন কাঠ',
            'category' => MaterialCategory::Wood,
            'unit' => MaterialUnit::Cft,
            'current_stock' => 0,
            'avg_cost' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ];
    }

    public function inStock(string $quantity, string $avgCost = '0.00'): static
    {
        return $this->state(fn () => [
            'current_stock' => $quantity,
            'avg_cost' => $avgCost,
        ]);
    }
}
