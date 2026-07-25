<?php

namespace App\Actions\ExpenseCategories;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $data
 */
class SaveExpenseCategory
{
    public function handle(array $data, ?ExpenseCategory $category = null): ExpenseCategory
    {
        return DB::transaction(function () use ($data, $category) {
            $category ??= new ExpenseCategory;

            $category->fill($data)->save();

            return $category;
        });
    }
}
