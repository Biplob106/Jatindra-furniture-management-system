<?php

namespace Database\Seeders;

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the first owner account so someone can log in and add the rest of
 * the staff from the users screen.
 *
 * Credentials come from config('app.owner'), fed by OWNER_NAME, OWNER_PHONE
 * and OWNER_PASSWORD in .env. Nothing is hardcoded, so this stays safe to run
 * against a live shop.
 *
 * Rerunnable: if a user with that phone already exists it only makes sure they
 * hold the owner role. It never resets their password or name, so changing the
 * password after the first login sticks.
 *
 * Depends on RolePermissionSeeder having run first.
 */
class OwnerUserSeeder extends Seeder
{
    public function run(CreateUser $createUser): void
    {
        $owner = config('app.owner');

        if (blank($owner['phone'] ?? null) || blank($owner['password'] ?? null)) {
            $this->command?->warn('OWNER_PHONE or OWNER_PASSWORD is not set; skipping the owner account.');

            return;
        }

        $existing = User::where('phone', $owner['phone'])->first();

        if ($existing) {
            if (! $existing->hasRole(Role::Owner->value)) {
                $existing->assignRole(Role::Owner->value);
            }

            return;
        }

        $createUser->handle(
            name: $owner['name'] ?: Role::Owner->label(),
            phone: $owner['phone'],
            password: $owner['password'],
            role: Role::Owner,
        );
    }
}
