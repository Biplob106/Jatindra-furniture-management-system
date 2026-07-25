<?php

namespace App\Enums;

/**
 * The four roles the shop runs on. Names match the spatie roles table rows
 * seeded by RolePermissionSeeder.
 */
enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Accountant = 'accountant';
    case Storekeeper = 'storekeeper';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'মালিক',
            self::Manager => 'ম্যানেজার',
            self::Accountant => 'হিসাবরক্ষক',
            self::Storekeeper => 'স্টোরকিপার',
        };
    }
}
