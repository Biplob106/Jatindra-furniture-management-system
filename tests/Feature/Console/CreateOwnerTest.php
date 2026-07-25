<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates the owner account', function () {
    $this->artisan('owner:create', [
        '--name' => 'জতীন্দ্র',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
    ])->assertSuccessful();

    $owner = User::first();

    expect(User::count())->toBe(1)
        ->and($owner->phone)->toBe('01811111111')
        ->and($owner->hasRole(Role::Owner->value))->toBeTrue();
});

it('refuses to create a second owner without force', function () {
    $this->artisan('owner:create', [
        '--name' => 'প্রথম',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
    ])->assertSuccessful();

    $this->artisan('owner:create', [
        '--name' => 'দ্বিতীয়',
        '--phone' => '01822222222',
        '--password' => 'shop-owner-password',
    ])->assertFailed();

    expect(User::count())->toBe(1);
});

it('creates a second owner when forced', function () {
    $this->artisan('owner:create', [
        '--name' => 'প্রথম',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
    ])->assertSuccessful();

    $this->artisan('owner:create', [
        '--name' => 'দ্বিতীয়',
        '--phone' => '01822222222',
        '--password' => 'shop-owner-password',
        '--force' => true,
    ])->assertSuccessful();

    expect(User::count())->toBe(2)
        ->and(User::role(Role::Owner->value)->count())->toBe(2);
});

it('rejects a duplicate phone number and writes nothing', function () {
    $this->artisan('owner:create', [
        '--name' => 'প্রথম',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
    ])->assertSuccessful();

    $this->artisan('owner:create', [
        '--name' => 'নকল',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
        '--force' => true,
    ])->assertFailed();

    expect(User::count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1);
});

it('rejects a weak password', function () {
    $this->artisan('owner:create', [
        '--name' => 'দুর্বল',
        '--phone' => '01811111111',
        '--password' => 'short',
    ])->assertFailed();

    expect(User::count())->toBe(0);
});

it('creates an owner who can see profit', function () {
    $this->artisan('owner:create', [
        '--name' => 'জতীন্দ্র',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
    ])->assertSuccessful();

    $owner = User::first();

    expect($owner->can('orders.profit'))->toBeTrue()
        ->and($owner->can('reports.financial'))->toBeTrue();
});

it('creates an owner who can log in', function () {
    $this->artisan('owner:create', [
        '--name' => 'জতীন্দ্র',
        '--phone' => '01811111111',
        '--password' => 'shop-owner-password',
    ])->assertSuccessful();

    $this->post('/login', [
        'phone' => '01811111111',
        'password' => 'shop-owner-password',
    ]);

    $this->assertAuthenticated();
});
