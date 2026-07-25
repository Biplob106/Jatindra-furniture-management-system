<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\ReferencedRecordException;
use Illuminate\Support\Facades\DB;

/**
 * A customer carrying an opening due is owed money and must not vanish. Orders
 * and sales join this check when phases 3 and 6 land.
 */
class DeleteCustomer
{
    public function handle(Customer $customer): void
    {
        if (bccomp((string) $customer->opening_due, '0.00', 2) !== 0) {
            throw new ReferencedRecordException(
                'এই কাস্টমারের বকেয়া আছে, তাই মুছে ফেলা যাবে না।'
            );
        }

        DB::transaction(fn () => $customer->delete());
    }
}
