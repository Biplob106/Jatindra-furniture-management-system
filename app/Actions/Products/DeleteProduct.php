<?php

namespace App\Actions\Products;

use App\Models\Product;
use App\Models\SaleItem;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * A product that has been sold, or is still standing on the floor, does not
 * vanish. Switch it off instead.
 *
 * Unlike materials, products soft-delete, so the movement log behind a removed
 * product still points at a row that can be read back. The sold check is still
 * worth making: an invoice whose product only exists withTrashed() is a
 * printable document that reads as damaged.
 */
class DeleteProduct
{
    public function handle(Product $product): void
    {
        if (SaleItem::where('product_id', $product->id)->exists()) {
            throw new ReferencedRecordException(
                'এই পণ্য বিক্রির হিসাবে আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        if (bccomp((string) $product->current_stock, '0.00', 2) !== 0) {
            throw new ReferencedRecordException(
                'এই পণ্য এখনো দোকানে আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        DB::transaction(fn () => $product->delete());
    }
}
