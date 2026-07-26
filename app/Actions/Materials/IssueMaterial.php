<?php

namespace App\Actions\Materials;

use App\Enums\MaterialMovementType;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Moves material out of the store, or back into it.
 *
 * Section 9: a material issue writes material_movements and nothing else. No
 * ledger, no transactions. The money left when the stock was bought; handing
 * timber to a carpenter moves no money at all.
 *
 * order_id is what makes per-order material cost answerable later, so it is
 * carried whenever the issue is against a job rather than general use.
 *
 * The movement is costed at the material's current average, not at whatever
 * the last challan charged. A job that consumed timber from three deliveries
 * cannot be told which planks came from which, and the average is the only
 * honest answer.
 */
class IssueMaterial
{
    /** What this action is allowed to write. An adjustment goes through AdjustStock. */
    private const ALLOWED = [
        MaterialMovementType::Out,
        MaterialMovementType::Wastage,
        MaterialMovementType::Return,
    ];

    public function handle(
        Material $material,
        string $quantity,
        string $movementDate,
        MaterialMovementType $type = MaterialMovementType::Out,
        ?Order $order = null,
        ?string $note = null,
        ?int $userId = null,
    ): MaterialMovement {
        if (! in_array($type, self::ALLOWED, true)) {
            throw new InvalidArgumentException("{$type->value} is not an issue. Use AdjustStock for a recount.");
        }

        $quantity = $this->quantity($quantity);

        if (bccomp($quantity, '0.000', 3) <= 0) {
            throw new InvalidArgumentException('An issue moves a positive quantity. Use the type to say which way.');
        }

        return DB::transaction(function () use ($material, $quantity, $movementDate, $type, $order, $note, $userId) {
            // Locked first, so two issues at once cannot both read the same
            // stock on hand and take it twice.
            $locked = Material::whereKey($material->id)->lockForUpdate()->firstOrFail();

            $sign = $type->sign();
            $newStock = bcadd((string) $locked->current_stock, bcmul($quantity, (string) $sign, 3), 3);

            // A store room cannot hand out what it does not have. Same rule
            // CashService applies to a cash box.
            if (bccomp($newStock, '0.000', 3) < 0) {
                throw new RuntimeException(
                    'গুদামে যত আছে তার বেশি দেওয়া যাবে না। এখন আছে '.$locked->current_stock.' '.$locked->unit->label().'।'
                );
            }

            $movement = MaterialMovement::create([
                'material_id' => $locked->id,
                'movement_date' => $movementDate,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $locked->avg_cost,
                'reference_type' => $order ? Order::class : null,
                'reference_id' => $order?->id,
                'order_id' => $order?->id,
                'note' => $note,
                'created_by' => $userId,
            ]);

            // Only the quantity moves. The average cost is what the stock was
            // bought at and an issue does not change it.
            $locked->forceFill(['current_stock' => $newStock])->save();

            $material->refresh();

            return $movement;
        });
    }

    private function quantity(string $value): string
    {
        if (! preg_match('/^-?\d+(\.\d{1,3})?$/', $value)) {
            throw new InvalidArgumentException("Refusing to move a non-decimal quantity: {$value}");
        }

        return number_format((float) $value, 3, '.', '');
    }
}
