<?php

namespace Database\Factories;

use App\Enums\WageType;
use App\Models\Employee;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => fake()->unique()->numerify('EMP-####'),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('016########'),
            'address' => fake()->address(),
            'photo' => null,
            'nid_no' => fake()->numerify('##########'),
            'trade_id' => Trade::factory(),
            'shop_id' => null,
            'wage_type' => WageType::Daily,
            'daily_rate' => 700,
            'monthly_salary' => 0,
            'joining_date' => fake()->dateTimeBetween('-3 years')->format('Y-m-d'),
            'guarantor_name' => fake()->name(),
            'guarantor_phone' => fake()->numerify('015########'),
            'opening_advance' => 0,
            'is_active' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => [
            'wage_type' => WageType::Monthly,
            'daily_rate' => 0,
            'monthly_salary' => 18000,
        ]);
    }

    public function piece(): static
    {
        return $this->state(fn () => [
            'wage_type' => WageType::Piece,
            'daily_rate' => 0,
            'monthly_salary' => 0,
        ]);
    }
}
