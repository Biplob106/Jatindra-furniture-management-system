<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product_categories.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->route('product_category');

        return [
            'name' => ['required', 'string', 'max:100'],
            // A category cannot be its own parent. Deeper cycles are not
            // reachable from the form, which only offers top level parents.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')->whereNull('deleted_at'),
                Rule::notIn($current ? [$current->id] : []),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'একটি ক্যাটাগরি নিজেই নিজের প্যারেন্ট হতে পারে না।',
        ];
    }
}
