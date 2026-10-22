<?php

use App\Enums\LedgerEntryType;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;
use App\Services\LedgerService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);

    $this->employee = Employee::factory()->create(['name' => 'সুমন মিস্ত্রি']);
    app(LedgerService::class)->record($this->employee, LedgerEntryType::WageEarned, '700.00', '2026-07-10');
});

it('shows the payroll report for a date range', function () {
    $this->actingAs($this->owner)
        ->get('/reports/payroll?from=2026-07-01&to=2026-07-31')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reports/payroll')
            ->where('totals.earned', '700.00')
            ->has('rows', 1));
});

it('exports the payroll report as a CSV download', function () {
    $response = $this->actingAs($this->owner)
        ->get('/reports/payroll?from=2026-07-01&to=2026-07-31&export=csv');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('attachment');
});

it('keeps a storekeeper out of the payroll report', function () {
    $storekeeper = User::factory()->create();
    $storekeeper->assignRole(Role::Storekeeper->value);

    $this->actingAs($storekeeper)->get('/reports/payroll')->assertForbidden();
});
