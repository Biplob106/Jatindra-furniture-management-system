<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Edits a staff account. Password is only touched when a new one is given.
 */
class UpdateUser
{
    public function handle(
        User $user,
        string $name,
        string $phone,
        Role $role,
        ?string $email = null,
        ?int $shopId = null,
        bool $isActive = true,
        ?string $password = null,
    ): User {
        return DB::transaction(function () use ($user, $name, $phone, $role, $email, $shopId, $isActive, $password) {
            $user->fill([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'shop_id' => $shopId,
                'is_active' => $isActive,
            ]);

            if ($password !== null && $password !== '') {
                $user->password = Hash::make($password);
            }

            $user->save();

            // syncRoles rather than assignRole: a user holds exactly one role.
            $user->syncRoles([$role->value]);

            return $user;
        });
    }
}
