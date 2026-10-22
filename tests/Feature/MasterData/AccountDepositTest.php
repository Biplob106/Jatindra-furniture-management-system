<?php

use App\Enums\Role;
use App\Models\Account;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->account = Account::factory()->create(['type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0]);
});

it('lets an accountant deposit cash into an account', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->post("/accounts/{$this->account->id}/deposit", ['amount' => '2000', 'txn_date' => '2026-10-22'])
        ->assertRedirect();

    expect($this->account->fresh()->current_balance)->toBe('2000.00');
});

it('rejects a zero deposit', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $this->actingAs($accountant)
        ->post("/accounts/{$this->account->id}/deposit", ['amount' => '0', 'txn_date' => '2026-10-22'])
        ->assertSessionHasErrors('amount');

    expect($this->account->fresh()->current_balance)->toBe('0.00');
});

it('keeps a storekeeper from depositing', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->post("/accounts/{$this->account->id}/deposit", ['amount' => '2000', 'txn_date' => '2026-10-22'])
        ->assertForbidden();
});
