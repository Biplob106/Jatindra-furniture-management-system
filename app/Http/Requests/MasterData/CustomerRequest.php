<?php

namespace App\Http\Requests\MasterData;

use App\Enums\CustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('customer')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            // Phone is the lookup key on the shop floor: staff find a customer
            // by reading the number off the order slip.
            //
            // The unique check deliberately counts soft-deleted rows, because
            // docs/schema.md puts a plain UNIQUE on the column. A deleted
            // customer keeps its number reserved so it can be restored.
            'phone' => ['required', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($id)],
            'alt_phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'area' => ['nullable', 'string', 'max:100'],
            'customer_type' => ['required', Rule::enum(CustomerType::class)],
            'opening_due' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string'],
        ];
    }
}
