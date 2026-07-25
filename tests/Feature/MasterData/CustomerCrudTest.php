<?php

use App\Enums\CustomerType;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

function customerPayload(array $overrides = []): array
{
    return [
        'name' => 'রহিম মিয়া',
        'phone' => '01712345678',
        'customer_type' => CustomerType::Retail->value,
        'opening_due' => 0,
        ...$overrides,
    ];
}

it('creates exactly one customer and stamps who added them', function () {
    $this->actingAs($this->owner)
        ->post('/customers', customerPayload())
        ->assertRedirect('/customers');

    $customer = Customer::sole();

    expect($customer->name)->toBe('রহিম মিয়া')
        ->and($customer->customer_type)->toBe(CustomerType::Retail)
        ->and($customer->created_by)->toBe($this->owner->id);
});

it('refuses a second customer on the same phone number', function () {
    Customer::factory()->create(['phone' => '01712345678']);

    $this->actingAs($this->owner)
        ->post('/customers', customerPayload())
        ->assertSessionHasErrors('phone');

    expect(Customer::count())->toBe(1);
});

/**
 * The column carries a plain UNIQUE in docs/schema.md, so the number stays
 * taken while the deleted row is still there to be restored. Validation has to
 * agree with the index or the insert dies on a duplicate key instead.
 */
it('keeps a phone number reserved after its customer is deleted', function () {
    $existing = Customer::factory()->create(['phone' => '01712345678', 'opening_due' => 0]);

    $this->actingAs($this->owner)->delete("/customers/{$existing->id}");

    $this->actingAs($this->owner)
        ->post('/customers', customerPayload())
        ->assertSessionHasErrors('phone');

    expect(Customer::count())->toBe(0)
        ->and(Customer::withTrashed()->count())->toBe(1);
});

it('keeps the opening due to the paisa', function () {
    $this->actingAs($this->owner)->post('/customers', customerPayload(['opening_due' => 1250.55]));

    expect(Customer::sole()->opening_due)->toBe('1250.55');
});

it('refuses to delete a customer who still owes money', function () {
    $customer = Customer::factory()->create(['opening_due' => 500]);

    $this->actingAs($this->owner)
        ->delete("/customers/{$customer->id}")
        ->assertSessionHas('error');

    expect(Customer::find($customer->id))->not->toBeNull();
});

it('deletes a customer who owes nothing', function () {
    $customer = Customer::factory()->create(['opening_due' => 0]);

    $this->actingAs($this->owner)->delete("/customers/{$customer->id}");

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull();
});

it('finds a customer by phone number, which is how the counter looks them up', function () {
    Customer::factory()->create(['name' => 'রহিম', 'phone' => '01712345678']);
    Customer::factory()->create(['name' => 'করিম', 'phone' => '01898765432']);

    $this->actingAs($this->owner)
        ->get('/customers?search=01712345678')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'রহিম'));
});

it('finds a customer by their alternate number too', function () {
    Customer::factory()->create(['name' => 'রহিম', 'phone' => '01712345678', 'alt_phone' => '01911112222']);
    Customer::factory()->create(['name' => 'করিম']);

    $this->actingAs($this->owner)
        ->get('/customers?search=01911112222')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('customers.data', 1)->where('customers.data.0.name', 'রহিম'));
});

it('lets a manager manage customers but a storekeeper only look', function () {
    $manager = User::factory()->create();
    $manager->assignRole(Role::Manager->value);

    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($manager)->get('/customers/create')->assertOk();
    $this->actingAs($storekeeper)->get('/customers')->assertForbidden();
});
