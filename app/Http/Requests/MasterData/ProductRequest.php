<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('product')?->id;

        $rules = [
            // The code printed on the tag. Unique across soft-deleted rows too,
            // because the column carries a plain UNIQUE and a switched-off
            // product keeps its code reserved.
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($id)],
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string'],
            'wood_type' => ['nullable', 'string', 'max:100'],
            'size_label' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'is_active' => ['required', 'boolean'],
        ];

        // Stock on hand is what stock_movements adds up to. The only figure
        // that may be typed is what was already on the floor on day one.
        if ($id === null) {
            $rules['opening_stock'] = ['required', 'numeric', 'min:0', 'max:99999999'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.unique' => 'এই কোড আরেকটি পণ্যের জন্য ব্যবহার হয়েছে।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku' => 'পণ্যের কোড',
            'name' => 'নাম',
            'category_id' => 'ক্যাটাগরি',
            'wood_type' => 'কাঠের ধরন',
            'size_label' => 'মাপ',
            'cost_price' => 'খরচ দর',
            'sale_price' => 'বিক্রয় দর',
            'min_stock' => 'সর্বনিম্ন মজুদ',
            'opening_stock' => 'এখন যত আছে',
        ];
    }
}
