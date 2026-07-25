<?php

namespace App\Http\Requests\Orders;

use App\Enums\DimensionUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    /**
     * Money the client sends for line_total, subtotal or total_amount is not
     * accepted at all: SaveOrder computes those. Only the inputs are validated.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'delivery_address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:order_items,id'],
            'items.*.item_name' => ['required', 'string', 'max:200'],
            'items.*.category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.wood_type' => ['nullable', 'string', 'max:100'],
            'items.*.design_no' => ['nullable', 'string', 'max:50'],
            'items.*.length' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.width' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.height' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'items.*.dimension_unit' => ['nullable', Rule::enum(DimensionUnit::class)],
            'items.*.polish_type' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'items.*.target_date' => ['nullable', 'date'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'অন্তত একটি আইটেম যোগ করুন।',
            'items.min' => 'অন্তত একটি আইটেম যোগ করুন।',
            'items.*.item_name.required' => 'আইটেমের নাম দিন।',
            'items.*.quantity.gt' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'expected_delivery_date.after_or_equal' => 'ডেলিভারির তারিখ অর্ডারের তারিখের আগে হতে পারে না।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'কাস্টমার',
            'shop_id' => 'দোকান',
            'order_date' => 'অর্ডারের তারিখ',
            'expected_delivery_date' => 'ডেলিভারির তারিখ',
            'discount' => 'ছাড়',
            'delivery_charge' => 'ডেলিভারি খরচ',
        ];
    }
}
