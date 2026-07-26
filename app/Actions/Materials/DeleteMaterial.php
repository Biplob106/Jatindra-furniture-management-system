<?php

namespace App\Actions\Materials;

use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\PurchaseItem;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * Removes a material that was never used.
 *
 * materials is the one piece of master data docs/schema.md gives no deleted_at
 * column, so there is no soft delete to fall back on. A row with any history
 * behind it — a movement, a challan line, or stock still on the floor — is
 * refused and must be switched off instead. What is left is a typo, and
 * deleting a typo loses nothing.
 */
class DeleteMaterial
{
    public function handle(Material $material): void
    {
        if (MaterialMovement::where('material_id', $material->id)->exists()) {
            throw new ReferencedRecordException(
                'এই মালামালের হিসাব আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        if (PurchaseItem::where('item_id', $material->id)->exists()) {
            throw new ReferencedRecordException(
                'এই মালামাল কেনার হিসাবে আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        if (bccomp((string) $material->current_stock, '0.000', 3) !== 0) {
            throw new ReferencedRecordException(
                'এই মালামাল এখনো মজুদ আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        DB::transaction(fn () => $material->delete());
    }
}
