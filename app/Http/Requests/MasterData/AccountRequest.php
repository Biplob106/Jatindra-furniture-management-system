<?php

namespace App\Http\Requests\MasterData;

use App\Enums\AccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounts.manage') ?? false;
    }

    /**
     * opening_balance is only accepted on create. Editing it after the fact
     * would silently desync current_balance, which CashService owns.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'account_no' => ['nullable', 'string', 'max:50'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'is_active' => ['required', 'boolean'],
        ];

        if ($this->routeIs('accounts.store')) {
            $rules['opening_balance'] = ['required', 'numeric', 'min:0', 'max:999999999999'];
        }

        return $rules;
    }
}
