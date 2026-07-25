<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates a staff account and assigns its role.
 *
 * Two tables are touched: users, and spatie's model_has_roles. They must land
 * together, so an unknown role name leaves no orphan user behind.
 */
class CreateUser
{
    public function handle(
        string $name,
        string $phone,
        string $password,
        Role $role,
        ?string $email = null,
        ?int $shopId = null,
    ): User {
        return DB::transaction(function () use ($name, $phone, $password, $role, $email, $shopId) {
            $user = User::create([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'password' => Hash::make($password),
                'shop_id' => $shopId,
                'is_active' => true,
            ]);

            $user->assignRole($role->value);

            return $user;
        });
    }
}
