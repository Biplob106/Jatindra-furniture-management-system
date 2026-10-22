<?php

namespace Database\Factories;

use App\Models\DeductionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeductionCategory>
 */
class DeductionCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }
}
