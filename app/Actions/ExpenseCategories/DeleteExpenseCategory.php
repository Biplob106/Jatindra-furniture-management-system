<?php

namespace App\Actions\ExpenseCategories;

use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;

/**
 * Nothing references an expense category yet. The expenses table lands in
 * phase 4 and this guard grows a check for it then.
 */
class DeleteExpenseCategory
{
    public function handle(ExpenseCategory $category): void
    {
        DB::transaction(fn () => $category->delete());
    }
}
