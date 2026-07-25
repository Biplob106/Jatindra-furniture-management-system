<?php

namespace App\Http\Requests\Users;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(Role::class)],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
