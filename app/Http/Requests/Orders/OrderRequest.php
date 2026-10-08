<?php

namespace App\Http\Requests\Orders;

use App\Enums\DimensionUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

            // Images for this item. Keyed under the item so a photo can only
            // ever arrive attached to the row it was picked in.
            'items.*.new_photos' => ['nullable', 'array', 'max:10'],
            'items.*.new_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'items.*.new_designs' => ['nullable', 'array', 'max:10'],
            'items.*.new_designs.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            // Ownership is checked in SaveOrder against the item itself.
            'items.*.remove_media' => ['nullable', 'array'],
            'items.*.remove_media.*' => ['integer'],
            // How many image files the form sent; checked in after().
            'expected_files' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * PHP silently drops uploads beyond max_file_uploads, and validation never
     * sees what was dropped. The form states how many files it sent, so a
     * shortfall refuses the save instead of storing an order with pictures
     * quietly missing.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('expected_files') === null) {
                    return;
                }

                $received = 0;

                foreach ($this->file('items') ?? [] as $item) {
                    $received += count($item['new_photos'] ?? []) + count($item['new_designs'] ?? []);
                }

                if ($received < (int) $this->input('expected_files')) {
                    $validator->errors()->add(
                        'expected_files',
                        'সব ছবি সার্ভারে পৌঁছায়নি। একবারে কম ছবি দিয়ে আবার সংরক্ষণ করুন।'
                    );
                }
            },
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
            'items.*.new_photos.max' => 'একটি পণ্যে একবারে সর্বোচ্চ ১০টি ছবি দেওয়া যাবে।',
            'items.*.new_designs.max' => 'একটি পণ্যে একবারে সর্বোচ্চ ১০টি নকশা দেওয়া যাবে।',
            'items.*.new_photos.*.image' => 'শুধু ছবি দেওয়া যাবে।',
            'items.*.new_designs.*.image' => 'শুধু ছবি দেওয়া যাবে।',
            'items.*.new_photos.*.mimes' => 'ছবি jpg, png বা webp হতে হবে।',
            'items.*.new_designs.*.mimes' => 'ছবি jpg, png বা webp হতে হবে।',
            'items.*.new_photos.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ১০ এমবি হতে পারে।',
            'items.*.new_designs.*.max' => 'প্রতিটি ছবি সর্বোচ্চ ১০ এমবি হতে পারে।',
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
