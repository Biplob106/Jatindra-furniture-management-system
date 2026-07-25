<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('expense_categories.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'is_recurring' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
