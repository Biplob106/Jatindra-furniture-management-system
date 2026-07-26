<?php

namespace App\Http\Requests\Materials;

use App\Enums\MaterialMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.adjust') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'material_id' => ['required', 'integer', 'exists:materials,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            // A recount is not an issue: it goes through the count form, where
            // the direction comes from what was counted.
            'type' => ['required', Rule::in([
                MaterialMovementType::Out->value,
                MaterialMovementType::Wastage->value,
                MaterialMovementType::Return->value,
            ])],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'material_id.required' => 'কোন মালামাল তা বেছে নিন।',
            'quantity.gt' => 'পরিমাণ শূন্যের বেশি হতে হবে।',
            'movement_date.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
            'type.in' => 'গণনার সংশোধন আলাদা ফর্মে লিখুন।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'material_id' => 'মালামাল',
            'quantity' => 'পরিমাণ',
            'movement_date' => 'তারিখ',
            'order_id' => 'অর্ডার',
        ];
    }
}
