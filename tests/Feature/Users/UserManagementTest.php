<?php

use App\Enums\Role;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

it('has no public registration route', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

it('keeps every user route behind the users.manage permission', function (string $method, string $uri) {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $this->actingAs($manager)->call($method, $uri)->assertForbidden();
})->with([
    ['get', '/users'],
    ['get', '/users/create'],
    ['post', '/users'],
]);

it('redirects a guest to login rather than showing the user list', function () {
    $this->get('/users')->assertRedirect('/login');
});

it('lets the owner see the user list', function () {
    $this->actingAs($this->owner)->get('/users')->assertOk();
});

it('creates a user with exactly one row and one role assignment', function () {
    $before = User::count();

    $this->actingAs($this->owner)
        ->post('/users', [
            'name' => 'নতুন ম্যানেজার',
            'phone' => '01899999999',
            'password' => 'manager-password',
            'password_confirmation' => 'manager-password',
            'role' => Role::Manager->value,
        ])
        ->assertRedirect('/users');

    expect(User::count())->toBe($before + 1);

    $created = User::where('phone', '01899999999')->first();

    expect($created->hasRole(Role::Manager->value))->toBeTrue()
        ->and($created->is_active)->toBeTrue()
        ->and($created->email)->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $created->id)->count())->toBe(1);
});

it('rejects a duplicate phone number and writes nothing', function () {
    $before = User::count();

    $this->actingAs($this->owner)
        ->post('/users', [
            'name' => 'নকল',
            'phone' => $this->owner->phone,
            'password' => 'some-password-here',
            'password_confirmation' => 'some-password-here',
            'role' => Role::Manager->value,
        ])
        ->assertSessionHasErrors('phone');

    expect(User::count())->toBe($before);
});

it('rejects an unknown role', function () {
    $this->actingAs($this->owner)
        ->post('/users', [
            'name' => 'ভুল পদ',
            'phone' => '01877777777',
            'password' => 'some-password-here',
            'password_confirmation' => 'some-password-here',
            'role' => 'superuser',
        ])
        ->assertSessionHasErrors('role');

    expect(User::where('phone', '01877777777')->exists())->toBeFalse();
});

it('assigns a user to a shop', function () {
    $shop = Shop::factory()->create();

    $this->actingAs($this->owner)->post('/users', [
        'name' => 'দোকানের কর্মী',
        'phone' => '01866666666',
        'password' => 'some-password-here',
        'password_confirmation' => 'some-password-here',
        'role' => Role::Storekeeper->value,
        'shop_id' => $shop->id,
    ]);

    expect(User::where('phone', '01866666666')->first()->shop_id)->toBe($shop->id);
});

it('updates a user without touching the password when none is given', function () {
    $staff = User::factory()->create(['name' => 'পুরনো নাম']);
    $staff->assignRole(Role::Storekeeper->value);
    $original = $staff->password;

    $this->actingAs($this->owner)
        ->put("/users/{$staff->id}", [
            'name' => 'নতুন নাম',
            'phone' => $staff->phone,
            'role' => Role::Accountant->value,
            'is_active' => true,
        ])
        ->assertRedirect('/users');

    $staff->refresh();

    expect($staff->name)->toBe('নতুন নাম')
        ->and($staff->password)->toBe($original)
        ->and($staff->hasRole(Role::Accountant->value))->toBeTrue()
        ->and($staff->hasRole(Role::Storekeeper->value))->toBeFalse()
        ->and($staff->roles()->count())->toBe(1);
});

it('changes the password when a new one is given', function () {
    $staff = User::factory()->create();
    $staff->assignRole(Role::Manager->value);

    $this->actingAs($this->owner)->put("/users/{$staff->id}", [
        'name' => $staff->name,
        'phone' => $staff->phone,
        'role' => Role::Manager->value,
        'is_active' => true,
        'password' => 'a-brand-new-password',
        'password_confirmation' => 'a-brand-new-password',
    ]);

    expect(Hash::check('a-brand-new-password', $staff->fresh()->password))->toBeTrue();
});

it('deactivates a user instead of deleting the row', function () {
    $staff = User::factory()->create();
    $staff->assignRole(Role::Manager->value);

    $this->actingAs($this->owner)->delete("/users/{$staff->id}");

    expect(User::find($staff->id))->not->toBeNull()
        ->and($staff->fresh()->is_active)->toBeFalse();
});

it('reactivates a user that was switched off', function () {
    $staff = User::factory()->inactive()->create();
    $staff->assignRole(Role::Manager->value);

    $this->actingAs($this->owner)->delete("/users/{$staff->id}");

    expect($staff->fresh()->is_active)->toBeTrue();
});

it('refuses to deactivate your own account', function () {
    $this->actingAs($this->owner)
        ->delete("/users/{$this->owner->id}")
        ->assertSessionHas('error');

    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('allows switching one owner off while another is still active', function () {
    $secondOwner = User::factory()->create();
    $secondOwner->assignRole(Role::Owner->value);

    $this->actingAs($this->owner)->delete("/users/{$secondOwner->id}");

    expect($secondOwner->fresh()->is_active)->toBeFalse();
});

it('refuses to deactivate the last active owner', function () {
    // Someone other than the owner holding users.manage. Nobody does today,
    // but the guard must not depend on that staying true.
    $deputy = User::factory()->create();
    $deputy->assignRole(Role::Manager->value);
    $deputy->givePermissionTo('users.manage');

    expect(User::role(Role::Owner->value)->where('is_active', true)->count())->toBe(1);

    $this->actingAs($deputy)
        ->delete("/users/{$this->owner->id}")
        ->assertSessionHas('error');

    expect($this->owner->fresh()->is_active)->toBeTrue();
});

it('a deactivated user can no longer log in', function () {
    $staff = User::factory()->create();
    $staff->assignRole(Role::Manager->value);

    $this->actingAs($this->owner)->delete("/users/{$staff->id}");

    auth()->logout();

    $this->post('/login', ['phone' => $staff->phone, 'password' => 'password']);

    $this->assertGuest();
});
