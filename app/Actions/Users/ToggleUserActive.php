<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a staff account on or off.
 *
 * Users are never deleted. An account that is switched off cannot log in but
 * stays attached to every order, ledger row and closing it was involved in.
 */
class ToggleUserActive
{
    public function handle(User $user, bool $isActive): User
    {
        return DB::transaction(function () use ($user, $isActive) {
            $user->update(['is_active' => $isActive]);

            return $user;
        });
    }
}
