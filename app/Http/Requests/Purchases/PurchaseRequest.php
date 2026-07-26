<?php

namespace App\Http\Requests\Purchases;

use App\Enums\CashPaymentMethod;
use App\Enums\PurchasePaymentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchases.record') ?? false;
    }

    /**
     * Line totals, the subtotal and the challan total are not accepted from the
     * client at all: RecordPurchase computes them. Only the inputs are here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $paid = $this->enum('payment_type', PurchasePaymentType::class);
        $movesMoney = $paid !== PurchasePaymentType::Credit;

        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'reference_no' => ['nullable', 'string', 'max:50'],
            'payment_type' => ['required', Rule::enum(PurchasePaymentType::class)],
            'payment_due_date' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'transport_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'note' => ['nullable', 'string'],

            // An account is only needed when money actually leaves. A credit
            // purchase has none, which is the whole point of it.
            'account_id' => [Rule::requiredIf($movesMoney), 'nullable', 'integer', 'exists:accounts,id'],
            'payment_method' => ['nullable', Rule::enum(CashPaymentMethod::class)],
            'paid_amount' => [
                Rule::requiredIf($paid === PurchasePaymentType::Partial),
                'nullable', 'numeric', 'gt:0', 'max:9999999999',
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit' => ['nullable', 'string', 'max:20'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'অন্তত একটি মালামাল যোগ করুন।',
            'items.min' => 'অন্তত একটি মালামাল যোগ করুন।',
            'items.*.item_id.required' => 'কোন মালামাল কেনা হয়েছে তা বেছে নিন।',
            'items.*.quantity.gt' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'account_id.required' => 'টাকা কোন হিসাব থেকে গেছে তা বেছে নিন।',
            'paid_amount.required' => 'কত টাকা দেওয়া হয়েছে তা লিখুন।',
            'paid_amount.gt' => 'আংশিক পরিশোধ শূন্যের বেশি হতে হবে।',
            'purchase_date.before_or_equal' => 'ভবিষ্যতের তারিখে কেনা দেখানো যাবে না।',
            'payment_due_date.after_or_equal' => 'শোধের তারিখ কেনার তারিখের আগে হতে পারে না।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'সরবরাহকারী',
            'purchase_date' => 'কেনার তারিখ',
            'reference_no' => 'চালান নম্বর',
            'payment_type' => 'পরিশোধের ধরন',
            'payment_due_date' => 'শোধের তারিখ',
            'transport_cost' => 'পরিবহন খরচ',
            'discount' => 'ছাড়',
            'account_id' => 'হিসাব',
            'paid_amount' => 'পরিশোধিত টাকা',
        ];
    }
}
