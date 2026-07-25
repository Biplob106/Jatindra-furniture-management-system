<?php

namespace App\Http\Requests\MasterData;

use App\Enums\WageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employees.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('employee')?->id;

        return [
            // Counts soft-deleted rows: the column carries a plain UNIQUE in
            // docs/schema.md, so a deleted employee keeps its code.
            'employee_code' => ['required', 'string', 'max:20', Rule::unique('employees', 'employee_code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'nid_no' => ['nullable', 'string', 'max:30'],
            'trade_id' => ['nullable', 'integer', 'exists:trades,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'wage_type' => ['required', Rule::enum(WageType::class)],
            // Required only for the wage type that actually uses it. The Action
            // zeroes the other one so a stale rate cannot pay a worker twice.
            'daily_rate' => ['nullable', 'required_if:wage_type,'.WageType::Daily->value, 'numeric', 'min:0', 'max:99999999'],
            'monthly_salary' => ['nullable', 'required_if:wage_type,'.WageType::Monthly->value, 'numeric', 'min:0', 'max:9999999999'],
            'joining_date' => ['nullable', 'date'],
            'guarantor_name' => ['nullable', 'string', 'max:150'],
            'guarantor_phone' => ['nullable', 'string', 'max:20'],
            'opening_advance' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
