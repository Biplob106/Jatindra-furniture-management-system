<?php

namespace App\Actions\Employees;

use App\Enums\WageType;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an employee.
 *
 * Rates are normalised to the wage type so a worker cannot carry both a daily
 * rate and a monthly salary. The wage automation reads whichever one the type
 * says is live, and a stale value in the other column is money waiting to be
 * paid twice.
 *
 * @param  array<string, mixed>  $data
 */
class SaveEmployee
{
    public function handle(array $data, ?Employee $employee = null): Employee
    {
        $wageType = $data['wage_type'] instanceof WageType
            ? $data['wage_type']
            : WageType::from($data['wage_type']);

        $data['daily_rate'] = $wageType === WageType::Daily ? ($data['daily_rate'] ?? 0) : 0;
        $data['monthly_salary'] = $wageType === WageType::Monthly ? ($data['monthly_salary'] ?? 0) : 0;

        return DB::transaction(function () use ($data, $employee) {
            $employee ??= new Employee;

            $employee->fill($data)->save();

            return $employee;
        });
    }
}
