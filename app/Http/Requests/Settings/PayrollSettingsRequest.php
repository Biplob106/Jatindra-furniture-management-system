<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class PayrollSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A fraction of the daily wage. 0.5 is half; 1 would pay a full
            // day's wage for a night. Capped at a sane ceiling.
            'night_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'night_multiplier.min' => 'রাত্রি ভাতার হার শূন্যের কম হতে পারে না।',
        ];
    }
}
