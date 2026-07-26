<?php

namespace Database\Factories;

use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'business_name' => null,
            'phone' => '01'.fake()->numerify('#########'),
            'address' => null,
            'supplier_type' => SupplierType::Wood,
            'opening_due' => 0,
            'credit_limit' => 0,
            'default_credit_days' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function onCredit(int $days, string $limit = '0.00'): static
    {
        return $this->state(fn () => [
            'default_credit_days' => $days,
            'credit_limit' => $limit,
        ]);
    }
}
