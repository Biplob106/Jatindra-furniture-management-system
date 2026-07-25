<?php

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;

class TradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('trades.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'default_daily_rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
