<?php

namespace Database\Factories;

use App\Enums\MaterialUnit;
use App\Enums\PurchaseItemType;
use App\Models\Material;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_id' => Purchase::factory(),
            'item_type' => PurchaseItemType::Material,
            'item_id' => Material::factory(),
            'quantity' => '10.000',
            'unit' => MaterialUnit::Cft->value,
            'unit_price' => '1200.00',
            'line_total' => '12000.00',
            'note' => null,
        ];
    }
}
