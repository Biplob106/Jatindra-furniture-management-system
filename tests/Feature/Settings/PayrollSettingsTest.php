<?php

use App\Enums\Role;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

it('shows the payroll settings page to the owner', function () {
    $this->actingAs($this->owner)
        ->get('/settings/payroll')
        ->assertOk();
});

it('saves the night allowance multiplier', function () {
    $this->actingAs($this->owner)
        ->patch('/settings/payroll', ['night_multiplier' => '0.6'])
        ->assertRedirect();

    expect(Setting::read('payroll.night_multiplier', '0.5'))->toBe('0.6');
});

it('rejects a negative multiplier', function () {
    $this->actingAs($this->owner)
        ->patch('/settings/payroll', ['night_multiplier' => '-1'])
        ->assertSessionHasErrors('night_multiplier');
});

it('keeps non-owners out of payroll settings', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)->get('/settings/payroll')->assertForbidden();
    $this->actingAs($accountant)->patch('/settings/payroll', ['night_multiplier' => '0.6'])->assertForbidden();
});
