<?php

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    Carbon::setTestNow('2026-07-20 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Every controller reports success by flashing a Bengali message and
 * redirecting. If the shared prop does not survive the redirect, all of those
 * messages are silently invisible.
 */
it('shows the success message on the page the redirect lands on', function () {
    $employee = Employee::factory()->create();

    $response = $this->actingAs($this->owner)->post('/attendance', [
        'work_date' => '2026-07-20',
        'rows' => [['employee_id' => $employee->id, 'status' => 'present']],
    ]);

    $response->assertSessionHas('success', 'হাজিরা সংরক্ষণ করা হয়েছে।');

    $this->actingAs($this->owner)
        ->get($response->headers->get('Location'))
        ->assertInertia(fn ($page) => $page->where('flash.success', 'হাজিরা সংরক্ষণ করা হয়েছে।'));
});

it('leaves flash empty when nothing was flashed', function () {
    $this->actingAs($this->owner)
        ->get('/attendance')
        ->assertInertia(fn ($page) => $page->where('flash.success', null)->where('flash.error', null));
});

it('carries an error message through a blocked delete', function () {
    $customer = Customer::factory()->create(['opening_due' => 500]);

    $response = $this->actingAs($this->owner)->delete("/customers/{$customer->id}");

    $response->assertSessionHas('error');

    $this->actingAs($this->owner)
        ->get('/customers')
        ->assertInertia(fn ($page) => $page->whereNot('flash.error', null));
});
