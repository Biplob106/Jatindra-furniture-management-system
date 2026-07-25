<?php

namespace App\Http\Requests\Employees;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('employee_payment.record') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $movesCash = in_array($this->input('type'), ['advance', 'tiffin', 'payout'], true);

        return [
            'type' => ['required', Rule::enum(LedgerEntryType::class)->only([
                LedgerEntryType::Advance,
                LedgerEntryType::Tiffin,
                LedgerEntryType::Payout,
                LedgerEntryType::Fine,
                LedgerEntryType::Bonus,
            ])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            // Only the types that hand money over need somewhere to take it
            // from. A fine and a bonus move no cash at all.
            'account_id' => [$movesCash ? 'required' : 'nullable', 'integer', 'exists:accounts,id'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'টাকা কোন হিসাব থেকে দেওয়া হচ্ছে তা বেছে নিন।',
            'amount.gt' => 'টাকার পরিমাণ শূন্যের বেশি হতে হবে।',
            'entry_date.before_or_equal' => 'ভবিষ্যতের তারিখে টাকা দেওয়া যাবে না।',
        ];
    }
}
