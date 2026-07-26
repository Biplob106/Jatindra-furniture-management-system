<?php

namespace App\Actions\Materials;

use App\Enums\MaterialMovementType;
use App\Models\Material;
use App\Models\MaterialMovement;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a material.
 *
 * current_stock and avg_cost are never written from the form. They are what
 * material_movements adds up to, and a figure typed over them would make the
 * movement log a story nobody can check.
 *
 * The one exception is day one. The rollout checklist calls for opening
 * material stock to be seeded, so a new material may arrive with what is
 * already on the floor. That is written as an `in` movement with the cost it
 * was bought at, so even the opening figure has a row behind it.
 */
class SaveMaterial
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Material $material = null): Material
    {
        return DB::transaction(function () use ($data, $material) {
            $isNew = $material === null;
            $material ??= new Material;

            $material->fill($data)->save();

            if ($isNew) {
                $this->seedOpeningStock($material, $data);
            }

            return $material->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedOpeningStock(Material $material, array $data): void
    {
        $quantity = number_format((float) ($data['opening_stock'] ?? 0), 3, '.', '');
        $unitCost = number_format((float) ($data['opening_cost'] ?? 0), 2, '.', '');

        if (bccomp($quantity, '0.000', 3) <= 0) {
            return;
        }

        MaterialMovement::create([
            'material_id' => $material->id,
            'movement_date' => now()->toDateString(),
            'type' => MaterialMovementType::In,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'note' => 'খাতার আগের মজুদ',
        ]);

        $material->forceFill([
            'current_stock' => $quantity,
            'avg_cost' => $unitCost,
        ])->save();
    }
}
