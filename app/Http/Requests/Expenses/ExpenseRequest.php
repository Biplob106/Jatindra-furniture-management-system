<?php

namespace App\Http\Requests\Expenses;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('expenses.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'paid_to' => ['nullable', 'string', 'max:150'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'note' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.gt' => 'টাকার পরিমাণ শূন্যের বেশি হতে হবে।',
            'expense_date.before_or_equal' => 'ভবিষ্যতের তারিখে খরচ লেখা যাবে না।',
            'account_id.required' => 'টাকা কোন হিসাব থেকে গেল তা বেছে নিন।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'খরচের খাত',
            'account_id' => 'হিসাব',
            'expense_date' => 'তারিখ',
            'amount' => 'টাকার পরিমাণ',
            'paid_to' => 'কাকে দেওয়া হলো',
        ];
    }
}
