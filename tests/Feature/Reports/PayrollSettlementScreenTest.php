<?php

use App\Enums\LedgerEntryType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollSettlement;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->employee = Employee::factory()->create();
    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-10');
});

it('finalizes the period from the report', function () {
    $this->actingAs($this->owner)
        ->post('/reports/payroll/settle', ['from' => '2026-07-01', 'to' => '2026-07-31'])
        ->assertRedirect();

    expect(PayrollSettlement::whereNull('reversed_at')->count())->toBe(1);
});

it('reverses a finalized period', function () {
    $this->actingAs($this->owner)->post('/reports/payroll/settle', ['from' => '2026-07-01', 'to' => '2026-07-31']);

    $this->actingAs($this->owner)
        ->post('/reports/payroll/reverse', ['from' => '2026-07-01', 'to' => '2026-07-31'])
        ->assertRedirect();

    expect(PayrollSettlement::whereNull('reversed_at')->count())->toBe(0)
        ->and(PayrollSettlement::count())->toBe(1);
});

it('marks settled employees on the report', function () {
    $this->actingAs($this->owner)->post('/reports/payroll/settle', ['from' => '2026-07-01', 'to' => '2026-07-31']);

    $this->actingAs($this->owner)
        ->get('/reports/payroll?from=2026-07-01&to=2026-07-31')
        ->assertInertia(fn ($page) => $page
            ->where('settledIds', [$this->employee->id])
            ->where('canSettle', true));
});

it('keeps a storekeeper from finalizing payroll', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)
        ->post('/reports/payroll/settle', ['from' => '2026-07-01', 'to' => '2026-07-31'])
        ->assertForbidden();
});
