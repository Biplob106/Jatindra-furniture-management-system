<?php

namespace App\Actions\DeductionCategories;

use App\Models\DeductionCategory;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $data
 */
class SaveDeductionCategory
{
    public function handle(array $data, ?DeductionCategory $category = null): DeductionCategory
    {
        return DB::transaction(function () use ($data, $category) {
            $category ??= new DeductionCategory;

            $category->fill($data)->save();

            return $category;
        });
    }
}
