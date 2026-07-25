<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * An employee holding an opening advance is owed money in one direction or the
 * other. Once attendance and employee_ledger land in phase 2, any employee with
 * a single ledger row becomes undeletable too.
 */
class DeleteEmployee
{
    public function handle(Employee $employee): void
    {
        if (bccomp((string) $employee->opening_advance, '0.00', 2) !== 0) {
            throw new ReferencedRecordException(
                'এই কর্মীর অগ্রিম হিসাব বাকি আছে, তাই মুছে ফেলা যাবে না। বদলে নিষ্ক্রিয় করে দিন।'
            );
        }

        DB::transaction(fn () => $employee->delete());
    }
}
