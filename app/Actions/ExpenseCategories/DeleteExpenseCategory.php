<?php

namespace App\Actions\ExpenseCategories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

class DeleteExpenseCategory
{
    public function handle(ExpenseCategory $category): void
    {
        ReferencedRecordException::throwIfReferenced('খরচের খাত', [
            'খরচ' => Expense::where('category_id', $category->id)->count(),
        ]);

        DB::transaction(fn () => $category->delete());
    }
}
