<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderItemWorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    /**
     * Whether this worker may carry a contract amount is SaveItemWork's call,
     * since it depends on their wage type and getting it wrong pays someone
     * twice. Asking the question in two places invites two answers.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'trade_id' => ['nullable', 'integer', 'exists:trades,id'],
            'work_type' => ['nullable', 'string', 'max:100'],
            'agreed_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'assigned_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(OrderItemWorkStatus::class)],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'কর্মী',
            'agreed_amount' => 'চুক্তির টাকা',
            'status' => 'অবস্থা',
        ];
    }
}
