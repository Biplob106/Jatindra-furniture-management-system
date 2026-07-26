<?php

namespace App\Http\Requests\MasterData;

use App\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'supplier_type' => ['required', Rule::enum(SupplierType::class)],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'default_credit_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['required', 'boolean'],
        ];

        // The opening due is a day-one figure. Once the supplier exists it is
        // ledger history, and correcting it is an adjustment entry rather than
        // an edit to this field.
        if ($this->route('supplier') === null) {
            $rules['opening_due'] = ['required', 'numeric', 'min:0', 'max:9999999999'];
        }

        return $rules;
    }
}
