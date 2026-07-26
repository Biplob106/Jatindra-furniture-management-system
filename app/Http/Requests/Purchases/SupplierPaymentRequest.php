<?php

namespace App\Http\Requests\Purchases;

use App\Enums\CashPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('supplier_payment.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'payment_method' => ['nullable', Rule::enum(CashPaymentMethod::class)],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],

            // Optional. Left out, the payment clears the oldest challans first.
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'টাকার পরিমাণ শূন্যের বেশি হতে হবে।',
            'payment_date.before_or_equal' => 'ভবিষ্যতের তারিখে পরিশোধ দেখানো যাবে না।',
            'account_id.required' => 'টাকা কোন হিসাব থেকে গেছে তা বেছে নিন।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'amount' => 'টাকার পরিমাণ',
            'payment_date' => 'তারিখ',
            'account_id' => 'হিসাব',
            'reference_no' => 'রেফারেন্স',
        ];
    }
}
