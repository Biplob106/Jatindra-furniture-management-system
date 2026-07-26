<?php

namespace App\Http\Requests\Materials;

use Illuminate\Foundation\Http\FormRequest;

class StockCountRequest extends FormRequest
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
            // What is on the floor, not the difference. AdjustStock works out
            // which way it went.
            'counted_stock' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
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
            'counted_stock.required' => 'গুদামে কত আছে তা লিখুন।',
            'movement_date.before_or_equal' => 'ভবিষ্যতের তারিখ দেওয়া যাবে না।',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'material_id' => 'মালামাল',
            'counted_stock' => 'গণনায় পাওয়া পরিমাণ',
            'movement_date' => 'তারিখ',
        ];
    }
}
