<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address' => fake()->address(),
            'phone' => fake()->unique()->numerify('017########'),
            'monthly_rent' => fake()->randomFloat(2, 5000, 40000),
            'rent_due_day' => fake()->numberBetween(1, 28),
            'landlord_name' => fake()->name(),
            'landlord_phone' => fake()->numerify('019########'),
            'electricity_meter_no' => fake()->numerify('########'),
            'is_active' => true,
        ];
    }
}
