<?php

namespace App\Actions\Materials;

use App\Enums\MaterialMovementType;
use App\Models\Material;
use App\Models\MaterialMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Brings a material's stock in line with what was counted on the floor.
 *
 * The counted figure is what the shop believes; the difference is what gets
 * written. An adjustment is the only movement type that goes either way, which
 * is why it is a separate action from IssueMaterial: there the direction comes
 * from the type, here it comes from the count.
 *
 * A count matching what the books already say writes nothing. Re-counting the
 * same shelf twice must not leave two rows saying nothing happened.
 */
class AdjustStock
{
    public function handle(
        Material $material,
        string $countedStock,
        string $movementDate,
        ?string $note = null,
        ?int $userId = null,
    ): ?MaterialMovement {
        if (! preg_match('/^\d+(\.\d{1,3})?$/', $countedStock)) {
            throw new InvalidArgumentException("Refusing to count a non-decimal quantity: {$countedStock}");
        }

        $counted = number_format((float) $countedStock, 3, '.', '');

        return DB::transaction(function () use ($material, $counted, $movementDate, $note, $userId) {
            $locked = Material::whereKey($material->id)->lockForUpdate()->firstOrFail();

            $difference = bcsub($counted, (string) $locked->current_stock, 3);

            if (bccomp($difference, '0.000', 3) === 0) {
                return null;
            }

            // The row carries the size of the correction; the stock column
            // carries where it landed. A negative quantity would make the
            // movement log unreadable, so the sign lives in the arithmetic.
            $movement = MaterialMovement::create([
                'material_id' => $locked->id,
                'movement_date' => $movementDate,
                'type' => MaterialMovementType::Adjustment,
                'quantity' => ltrim($difference, '-'),
                'unit_cost' => $locked->avg_cost,
                'note' => $this->noteFor($difference, $locked, $counted, $note),
                'created_by' => $userId,
            ]);

            $locked->forceFill(['current_stock' => $counted])->save();

            $material->refresh();

            return $movement;
        });
    }

    /**
     * An adjustment row on its own cannot say which way it went, so the note
     * does. Without it a recount reads as an unexplained number.
     */
    private function noteFor(string $difference, Material $material, string $counted, ?string $given): string
    {
        $direction = bccomp($difference, '0.000', 3) > 0 ? 'বেশি পাওয়া গেছে' : 'কম পাওয়া গেছে';
        $stated = "গণনায় {$counted} {$material->unit->label()} — {$direction}";

        return $given ? "{$stated} — {$given}" : $stated;
    }
}
