<?php

namespace App\Actions\Products;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a readymade product.
 *
 * current_stock is never written from the form. It is what stock_movements
 * adds up to, and a figure typed over it would make the movement log a story
 * nobody can check.
 *
 * Day one is the exception, the same as it is for materials. What is already
 * standing on the floor is recorded as an `adjustment` movement rather than
 * `production_in` or `purchase_in`: nobody knows any more whether those pieces
 * were built or bought, and a count is an honest answer where a guess at their
 * history is not.
 */
class SaveProduct
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?Product $product = null, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($data, $product, $userId) {
            $isNew = $product === null;
            $product ??= new Product;

            $product->fill($data)->save();

            if ($isNew) {
                $this->seedOpeningStock($product, $data, $userId);
            }

            return $product->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedOpeningStock(Product $product, array $data, ?int $userId): void
    {
        $quantity = number_format((float) ($data['opening_stock'] ?? 0), 2, '.', '');

        if (bccomp($quantity, '0.00', 2) <= 0) {
            return;
        }

        StockMovement::create([
            'product_id' => $product->id,
            'shop_id' => $product->shop_id,
            'movement_date' => now()->toDateString(),
            'type' => StockMovementType::Adjustment,
            'quantity' => $quantity,
            'unit_cost' => $product->cost_price,
            'note' => 'খাতার আগের মজুদ',
            'created_by' => $userId,
        ]);

        $product->forceFill(['current_stock' => $quantity])->save();
    }
}
