<?php

namespace Database\Factories;

use App\Enums\MaterialMovementType;
use App\Models\Material;
use App\Models\MaterialMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialMovement>
 */
class MaterialMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'material_id' => Material::factory(),
            'movement_date' => '2026-07-20',
            'type' => MaterialMovementType::In,
            'quantity' => '10.000',
            'unit_cost' => '1200.00',
            'reference_type' => null,
            'reference_id' => null,
            'order_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }

    public function consumed(string $quantity): static
    {
        return $this->state(fn () => [
            'type' => MaterialMovementType::Out,
            'quantity' => $quantity,
        ]);
    }
}
