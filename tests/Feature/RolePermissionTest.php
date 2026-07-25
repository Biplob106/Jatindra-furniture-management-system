<?php

namespace Tests\Feature;

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_seeds_the_four_roles()
    {
        $this->assertSame(4, Role::count());

        foreach (RoleEnum::cases() as $case) {
            $this->assertDatabaseHas('roles', ['name' => $case->value, 'guard_name' => 'web']);
        }
    }

    public function test_owner_holds_every_permission()
    {
        $owner = Role::findByName(RoleEnum::Owner->value);

        $this->assertSame(
            count(RolePermissionSeeder::allPermissionNames()),
            $owner->permissions()->count()
        );
    }

    /**
     * The rule the whole permission design exists to protect.
     */
    public function test_no_role_but_owner_holds_a_profit_or_financial_permission()
    {
        $ownerOnly = RolePermissionSeeder::ownerOnlyPermissionNames();

        $this->assertNotEmpty($ownerOnly, 'nothing is being guarded, the filter is wrong');

        foreach (RoleEnum::cases() as $case) {
            if ($case === RoleEnum::Owner) {
                continue;
            }

            $held = Role::findByName($case->value)->permissions->pluck('name')->all();

            foreach ($ownerOnly as $name) {
                $this->assertNotContains($name, $held, "{$case->value} must not hold {$name}");
            }
        }
    }

    public function test_a_manager_can_take_an_order_but_cannot_see_its_profit()
    {
        $manager = User::factory()->create();
        $manager->assignRole(RoleEnum::Manager->value);

        $this->assertTrue($manager->can('orders.manage'));
        $this->assertFalse($manager->can('orders.profit'));
        $this->assertFalse($manager->can('reports.financial'));
    }

    public function test_an_owner_can_see_profit()
    {
        $owner = User::factory()->create();
        $owner->assignRole(RoleEnum::Owner->value);

        $this->assertTrue($owner->can('orders.profit'));
        $this->assertTrue($owner->can('reports.financial'));
    }

    public function test_running_the_seeder_twice_changes_nothing()
    {
        $roles = Role::count();
        $permissions = Permission::count();
        $assignments = DB::table('role_has_permissions')->count();

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($roles, Role::count());
        $this->assertSame($permissions, Permission::count());
        $this->assertSame($assignments, DB::table('role_has_permissions')->count());
    }

    /**
     * @return array<string, array<string>>
     */
    public static function nonOwnerRoles(): array
    {
        return [
            'manager' => ['manager'],
            'accountant' => ['accountant'],
            'storekeeper' => ['storekeeper'],
        ];
    }

    #[DataProvider('nonOwnerRoles')]
    public function test_every_seeded_role_permission_actually_exists(string $roleName)
    {
        $known = RolePermissionSeeder::allPermissionNames();

        foreach (RolePermissionSeeder::ROLE_PERMISSIONS[$roleName] as $name) {
            $this->assertContains($name, $known, "{$roleName} is granted {$name}, which is not a defined permission");
        }
    }
}
