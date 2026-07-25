<?php

namespace App\Actions\ProductCategories;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $data
 */
class SaveProductCategory
{
    public function handle(array $data, ?ProductCategory $category = null): ProductCategory
    {
        return DB::transaction(function () use ($data, $category) {
            $category ??= new ProductCategory;

            $category->fill($data)->save();

            return $category;
        });
    }
}
