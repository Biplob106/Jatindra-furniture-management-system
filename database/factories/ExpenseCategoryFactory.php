<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['দোকান ভাড়া', 'কারেন্ট বিল', 'চা-নাস্তা', 'ট্রান্সপোর্ট', 'মেশিন মেরামত', 'লাইসেন্স']),
            'is_recurring' => false,
            'is_active' => true,
        ];
    }
}
