<?php

namespace App\Actions\DeductionCategories;

use App\Models\DeductionCategory;
use App\Models\EmployeeLedger;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

class DeleteDeductionCategory
{
    public function handle(DeductionCategory $category): void
    {
        ReferencedRecordException::throwIfReferenced('কর্তনের খাত', [
            'হিসাব' => EmployeeLedger::where('deduction_category_id', $category->id)->count(),
        ]);

        DB::transaction(fn () => $category->delete());
    }
}
