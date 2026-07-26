<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => 'রেডিমেড আলমারি',
            'category_id' => null,
            'description' => null,
            'wood_type' => 'সেগুন',
            'size_label' => '৬ × ৩ ফুট',
            'cost_price' => '18000.00',
            'sale_price' => '25000.00',
            'current_stock' => 0,
            'min_stock' => 0,
            'shop_id' => null,
            'is_active' => true,
        ];
    }

    public function inStock(string $quantity, ?string $costPrice = null): static
    {
        return $this->state(fn () => array_filter([
            'current_stock' => $quantity,
            'cost_price' => $costPrice,
        ], fn ($value) => $value !== null));
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
