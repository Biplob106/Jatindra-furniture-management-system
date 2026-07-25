<?php

use App\Enums\Role;
use App\Enums\WageType;
use App\Models\Employee;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::Owner->value);
    $this->trade = Trade::factory()->create(['default_daily_rate' => 700]);
});

function employeePayload(array $overrides = []): array
{
    return [
        'employee_code' => 'EMP-0001',
        'name' => 'সুমন মিস্ত্রি',
        'wage_type' => WageType::Daily->value,
        'daily_rate' => 700,
        'opening_advance' => 0,
        'is_active' => true,
        ...$overrides,
    ];
}

it('creates exactly one employee', function () {
    $this->actingAs($this->owner)
        ->post('/employees', employeePayload(['trade_id' => $this->trade->id]))
        ->assertRedirect('/employees');

    $employee = Employee::sole();

    expect($employee->name)->toBe('সুমন মিস্ত্রি')
        ->and($employee->wage_type)->toBe(WageType::Daily)
        ->and($employee->daily_rate)->toBe('700.00')
        ->and($employee->trade_id)->toBe($this->trade->id);
});

it('requires a daily rate for a daily worker', function () {
    $this->actingAs($this->owner)
        ->post('/employees', employeePayload(['daily_rate' => null]))
        ->assertSessionHasErrors('daily_rate');

    expect(Employee::count())->toBe(0);
});

it('requires a monthly salary for a monthly worker', function () {
    $this->actingAs($this->owner)
        ->post('/employees', employeePayload(['wage_type' => WageType::Monthly->value, 'daily_rate' => null, 'monthly_salary' => null]))
        ->assertSessionHasErrors('monthly_salary');
});

/**
 * The rate a wage type does not use is forced to zero. A stale value in the
 * other column is money waiting to be paid twice by the wage automation.
 */
it('zeroes the rate the wage type does not use', function (string $wageType, string $expectedDaily, string $expectedMonthly) {
    $this->actingAs($this->owner)->post('/employees', employeePayload([
        'wage_type' => $wageType,
        'daily_rate' => 700,
        'monthly_salary' => 18000,
    ]));

    $employee = Employee::sole();

    expect($employee->daily_rate)->toBe($expectedDaily)
        ->and($employee->monthly_salary)->toBe($expectedMonthly);
})->with([
    'daily' => ['daily', '700.00', '0.00'],
    'monthly' => ['monthly', '0.00', '18000.00'],
    'piece' => ['piece', '0.00', '0.00'],
]);

it('clears the old rate when a worker moves from daily to monthly', function () {
    $employee = Employee::factory()->create(['wage_type' => WageType::Daily, 'daily_rate' => 700, 'monthly_salary' => 0]);

    $this->actingAs($this->owner)->put("/employees/{$employee->id}", employeePayload([
        'employee_code' => $employee->employee_code,
        'name' => $employee->name,
        'wage_type' => WageType::Monthly->value,
        'daily_rate' => 700,
        'monthly_salary' => 20000,
    ]));

    $employee->refresh();

    expect($employee->wage_type)->toBe(WageType::Monthly)
        ->and($employee->daily_rate)->toBe('0.00')
        ->and($employee->monthly_salary)->toBe('20000.00');
});

it('refuses a duplicate employee code', function () {
    Employee::factory()->create(['employee_code' => 'EMP-0001']);

    $this->actingAs($this->owner)
        ->post('/employees', employeePayload())
        ->assertSessionHasErrors('employee_code');

    expect(Employee::count())->toBe(1);
});

it('suggests the next employee code', function () {
    Employee::factory()->create(['employee_code' => 'EMP-0007']);

    $this->actingAs($this->owner)
        ->get('/employees/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('nextCode', 'EMP-0008'));
});

it('refuses to delete an employee holding an advance', function () {
    $employee = Employee::factory()->create(['opening_advance' => 3000]);

    $this->actingAs($this->owner)
        ->delete("/employees/{$employee->id}")
        ->assertSessionHas('error');

    expect(Employee::find($employee->id))->not->toBeNull();
});

it('deletes an employee with no advance', function () {
    $employee = Employee::factory()->create(['opening_advance' => 0]);

    $this->actingAs($this->owner)->delete("/employees/{$employee->id}");

    expect(Employee::find($employee->id))->toBeNull()
        ->and(Employee::withTrashed()->find($employee->id))->not->toBeNull();
});

it('finds an employee by code', function () {
    Employee::factory()->create(['name' => 'সুমন', 'employee_code' => 'EMP-0042']);
    Employee::factory()->create(['name' => 'রফিক']);

    $this->actingAs($this->owner)
        ->get('/employees?search=EMP-0042')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees.data', 1)->where('employees.data.0.name', 'সুমন'));
});

it('lets an accountant read the employee list but not edit it', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Accountant->value);

    $employee = Employee::factory()->create();

    $this->actingAs($accountant)->get('/employees')->assertOk();
    $this->actingAs($accountant)->get("/employees/{$employee->id}/edit")->assertForbidden();
    $this->actingAs($accountant)->delete("/employees/{$employee->id}")->assertForbidden();
});
