<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['খাট', 'আলমারি', 'সোফা', 'ড্রেসিং টেবিল', 'ডাইনিং টেবিল']),
            'parent_id' => null,
            'is_active' => true,
        ];
    }
}
