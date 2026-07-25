<?php

namespace App\Http\Requests\Orders;

use App\Enums\CashPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.payment') ?? false;
    }

    /**
     * The amount is checked against what is still owed inside
     * RecordOrderAdvance, where the order is locked and the due is derived
     * from the cash rows. Doing it here as well would be a second answer to
     * the same question, and the two could disagree.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['nullable', Rule::enum(CashPaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'টাকার পরিমাণ শূন্যের বেশি হতে হবে।',
            'account_id.required' => 'টাকা কোন হিসাবে জমা হচ্ছে তা বেছে নিন।',
            'paid_on.before_or_equal' => 'ভবিষ্যতের তারিখে টাকা নেওয়া যাবে না।',
        ];
    }
}
