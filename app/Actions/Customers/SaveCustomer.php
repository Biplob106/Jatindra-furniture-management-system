<?php

namespace App\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, mixed>  $data
 */
class SaveCustomer
{
    public function handle(array $data, ?Customer $customer = null, ?int $createdBy = null): Customer
    {
        return DB::transaction(function () use ($data, $customer, $createdBy) {
            if ($customer === null) {
                $customer = new Customer;
                $customer->created_by = $createdBy;
            }

            $customer->fill($data)->save();

            return $customer;
        });
    }
}
