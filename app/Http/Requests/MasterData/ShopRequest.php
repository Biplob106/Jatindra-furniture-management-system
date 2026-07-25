<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('shops.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'monthly_rent' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'rent_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'landlord_name' => ['nullable', 'string', 'max:150'],
            'landlord_phone' => ['nullable', 'string', 'max:20'],
            'electricity_meter_no' => ['nullable', 'string', 'max:50'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
