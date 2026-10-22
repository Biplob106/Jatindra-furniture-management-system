<?php

use App\Enums\AccountType;
use App\Models\Account;
use Database\Seeders\AccountSeeder;

it('creates one zero-balance cash account on a fresh shop', function () {
    $this->seed(AccountSeeder::class);

    $account = Account::sole();

    expect($account->type)->toBe(AccountType::Cash)
        ->and($account->current_balance)->toBe('0.00');
});

it('does nothing once an account already exists', function () {
    Account::factory()->create(['name' => 'আমার ব্যাংক']);

    $this->seed(AccountSeeder::class);

    expect(Account::count())->toBe(1)
        ->and(Account::sole()->name)->toBe('আমার ব্যাংক');
});
