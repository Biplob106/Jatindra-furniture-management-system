<?php

use App\Actions\Users\CreateUser;
use App\Enums\Role;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('writes exactly one user row and one role assignment', function () {
    $user = (new CreateUser)->handle(
        name: 'জতীন্দ্র',
        phone: '01811111111',
        password: 'shop-owner-password',
        role: Role::Owner,
    );

    expect(User::count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1)
        ->and($user->hasRole(Role::Owner->value))->toBeTrue();
});

it('hashes the password and never stores it in the clear', function () {
    $user = (new CreateUser)->handle(
        name: 'ম্যানেজার',
        phone: '01822222222',
        password: 'a-real-password',
        role: Role::Manager,
    );

    expect($user->password)->not->toBe('a-real-password')
        ->and(Hash::check('a-real-password', $user->password))->toBeTrue();
});

it('creates an active user with an optional email and shop', function () {
    $shop = Shop::factory()->create();

    $user = (new CreateUser)->handle(
        name: 'হিসাবরক্ষক',
        phone: '01833333333',
        password: 'accountant-password',
        role: Role::Accountant,
        email: 'accounts@example.com',
        shopId: $shop->id,
    );

    expect($user->is_active)->toBeTrue()
        ->and($user->email)->toBe('accounts@example.com')
        ->and($user->shop_id)->toBe($shop->id);
});

it('defaults email and shop to null', function () {
    $user = (new CreateUser)->handle(
        name: 'স্টোরকিপার',
        phone: '01866666666',
        password: 'storekeeper-password',
        role: Role::Storekeeper,
    );

    expect($user->email)->toBeNull()
        ->and($user->shop_id)->toBeNull();
});

it('leaves no user behind when the role assignment fails', function () {
    // Pull the roles out from under the action. The user row inserts, then
    // assignRole throws, and the transaction must take the insert with it.
    DB::table('roles')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(fn () => (new CreateUser)->handle(
        name: 'ভুল',
        phone: '01855555555',
        password: 'password-value',
        role: Role::Manager,
    ))->toThrow(RoleDoesNotExist::class);

    expect(User::count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
});
