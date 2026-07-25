<?php

namespace App\Actions\ProductCategories;

use App\Models\ProductCategory;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * Categories nest, so a parent with children is in use even though no product
 * exists yet. The products check lands with phase 6.
 */
class DeleteProductCategory
{
    public function handle(ProductCategory $category): void
    {
        ReferencedRecordException::throwIfReferenced('ক্যাটাগরি', [
            'উপ-ক্যাটাগরি' => ProductCategory::where('parent_id', $category->id)->count(),
        ]);

        DB::transaction(fn () => $category->delete());
    }
}
