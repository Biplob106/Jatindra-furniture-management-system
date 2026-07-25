<?php

namespace Database\Factories;

use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trade>
 */
class TradeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['বার্নিশ', 'নকশা', 'প্লেন কাঠ', 'সিএনসি', 'হেলপার']),
            'default_daily_rate' => fake()->randomFloat(2, 400, 1200),
            'is_active' => true,
        ];
    }
}
