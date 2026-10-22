<?php

use App\Enums\LedgerEntryType;
use App\Enums\Role;
use App\Models\DeductionCategory;
use App\Models\Employee;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
});

it('creates exactly one deduction category', function () {
    $this->actingAs($this->owner)
        ->post('/deduction-categories', ['name' => 'ইউনিফর্ম', 'is_active' => true])
        ->assertRedirect('/deduction-categories');

    expect(DeductionCategory::sole()->name)->toBe('ইউনিফর্ম');
});

it('requires a name', function () {
    $this->actingAs($this->owner)
        ->post('/deduction-categories', ['name' => '', 'is_active' => true])
        ->assertSessionHasErrors('name');

    expect(DeductionCategory::count())->toBe(0);
});

/**
 * A category used by a ledger row keeps the audit trail honest, so deleting it
 * is blocked with a Bengali message rather than orphaning the entry.
 */
it('refuses to delete a category referenced by a ledger entry', function () {
    $category = DeductionCategory::factory()->create();
    $employee = Employee::factory()->create();

    app(LedgerService::class)->record(
        employee: $employee,
        type: LedgerEntryType::Deduction,
        amount: '100.00',
        entryDate: '2026-07-20',
        deductionCategoryId: $category->id,
    );

    $this->actingAs($this->owner)
        ->delete("/deduction-categories/{$category->id}")
        ->assertSessionHas('error');

    expect(DeductionCategory::whereKey($category->id)->exists())->toBeTrue();
});

it('soft-deletes an unused category', function () {
    $category = DeductionCategory::factory()->create();

    $this->actingAs($this->owner)->delete("/deduction-categories/{$category->id}");

    expect(DeductionCategory::whereKey($category->id)->exists())->toBeFalse()
        ->and(DeductionCategory::withTrashed()->whereKey($category->id)->exists())->toBeTrue();
});

it('keeps a storekeeper out of managing categories', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->post('/deduction-categories', ['name' => 'যা হোক', 'is_active' => true])
        ->assertForbidden();
});
