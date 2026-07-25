<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('017########'),
            'alt_phone' => null,
            'address' => fake()->address(),
            'area' => fake()->city(),
            'customer_type' => CustomerType::Retail,
            'opening_due' => 0,
            'note' => null,
            'created_by' => null,
        ];
    }
}
