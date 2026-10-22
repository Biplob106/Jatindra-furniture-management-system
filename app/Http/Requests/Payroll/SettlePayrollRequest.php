<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class SettlePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.settle') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
        ];
    }
}
