<?php

namespace App\Http\Requests\MasterData;

use App\Enums\MaterialCategory;
use App\Enums\MaterialUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('materials.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(MaterialCategory::class)],
            'unit' => ['required', Rule::enum(MaterialUnit::class)],
            'min_stock' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['required', 'boolean'],
        ];

        // Stock on hand is what material_movements adds up to. The only figure
        // that may be typed is what was already on the floor on day one, and
        // that becomes a movement of its own.
        if ($this->route('material') === null) {
            $rules['opening_stock'] = ['required', 'numeric', 'min:0', 'max:999999999'];
            $rules['opening_cost'] = ['required', 'numeric', 'min:0', 'max:9999999999'];
        }

        return $rules;
    }
}
