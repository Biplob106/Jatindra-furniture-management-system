<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only structural data belongs here: roles, permissions, and the reference
     * rows the app cannot run without. Demo records go in their own seeder so
     * this stays safe to run against a live shop.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
