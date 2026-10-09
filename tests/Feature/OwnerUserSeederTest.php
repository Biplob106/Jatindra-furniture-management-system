<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\OwnerUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    config()->set('app.owner', [
        'name' => 'জতীন্দ্র',
        'phone' => '01700000000',
        'password' => 'secret-pass',
    ]);
});

it('creates one active owner from config', function () {
    $this->seed(OwnerUserSeeder::class);

    expect(User::count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1);

    $owner = User::sole();

    expect($owner->name)->toBe('জতীন্দ্র')
        ->and($owner->phone)->toBe('01700000000')
        ->and($owner->is_active)->toBeTrue()
        ->and($owner->shop_id)->toBeNull()
        ->and($owner->hasRole(Role::Owner->value))->toBeTrue()
        ->and(Hash::check('secret-pass', $owner->password))->toBeTrue();
});

it('lets the seeded owner log in with phone and password', function () {
    $this->seed(OwnerUserSeeder::class);

    $this->post('/login', ['phone' => '01700000000', 'password' => 'secret-pass']);

    $this->assertAuthenticatedAs(User::sole());
});

it('does not duplicate or reset the owner when run twice', function () {
    $this->seed(OwnerUserSeeder::class);

    User::sole()->update(['password' => Hash::make('changed-after-login')]);

    $this->seed(OwnerUserSeeder::class);

    expect(User::count())->toBe(1)
        ->and(DB::table('model_has_roles')->count())->toBe(1)
        ->and(Hash::check('changed-after-login', User::sole()->password))->toBeTrue();
});

it('grants the owner role to an existing user with that phone', function () {
    $user = User::factory()->create(['phone' => '01700000000']);

    $this->seed(OwnerUserSeeder::class);

    expect(User::count())->toBe(1)
        ->and($user->fresh()->hasRole(Role::Owner->value))->toBeTrue();
});

it('skips when phone or password is missing', function (?string $phone, ?string $password) {
    config()->set('app.owner.phone', $phone);
    config()->set('app.owner.password', $password);

    $this->seed(OwnerUserSeeder::class);

    expect(User::count())->toBe(0)
        ->and(DB::table('model_has_roles')->count())->toBe(0);
})->with([
    'no phone' => [null, 'secret-pass'],
    'no password' => ['01700000000', null],
    'empty strings' => ['', ''],
]);
