<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the four roles and their permissions.
 *
 * Rerunnable: everything is firstOrCreate and syncPermissions, so running it
 * again after adding a permission below updates the roles rather than
 * duplicating rows.
 *
 * The one rule that must never drift: no role other than owner may hold a
 * permission whose name contains `profit` or `reports.financial`. There is a
 * test asserting exactly that.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Every permission in the system, grouped only for readability.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSIONS = [
        'master data' => [
            'shops.view', 'shops.manage',
            'users.view', 'users.manage',
            'customers.view', 'customers.manage',
            'employees.view', 'employees.manage',
            'trades.view', 'trades.manage',
            'accounts.view', 'accounts.manage',
            'expense_categories.view', 'expense_categories.manage',
            'product_categories.view', 'product_categories.manage',
        ],
        'labour' => [
            'attendance.view', 'attendance.mark',
            'employee_ledger.view', 'employee_payment.record',
            'salary.generate',
        ],
        'orders' => [
            'orders.view', 'orders.manage', 'orders.delivery',
            // Taking money against an order. Separate from orders.manage
            // because editing what was ordered and accepting cash for it are
            // different acts, and from transactions.record because the person
            // at the counter takes the advance without being the bookkeeper.
            'orders.payment',
        ],
        'cash' => [
            'transactions.view', 'transactions.record',
            'expenses.view', 'expenses.record',
            'daily_closing.view', 'daily_closing.run',
        ],
        'purchase' => [
            'suppliers.view', 'suppliers.manage',
            'materials.view', 'materials.manage',
            'purchases.view', 'purchases.record',
            'supplier_ledger.view', 'supplier_payment.record',
        ],
        'retail' => [
            'products.view', 'products.manage',
            'stock.view', 'stock.adjust',
            'sales.view', 'sales.record',
            'cnc_jobs.view', 'cnc_jobs.manage',
        ],
        'reports' => [
            'reports.operational',
            // Owner only, both of them.
            'reports.financial',
            'orders.profit',
        ],
    ];

    /**
     * Permissions per role. owner is not listed because owner gets all of them.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_PERMISSIONS = [
        'manager' => [
            'shops.view',
            'customers.view', 'customers.manage',
            'employees.view', 'employees.manage',
            'trades.view', 'trades.manage',
            'product_categories.view', 'product_categories.manage',
            'attendance.view', 'attendance.mark',
            'employee_ledger.view', 'employee_payment.record',
            'orders.view', 'orders.manage', 'orders.delivery', 'orders.payment',
            'expenses.view', 'expenses.record',
            'suppliers.view', 'materials.view',
            'purchases.view', 'purchases.record',
            'supplier_ledger.view',
            'products.view', 'stock.view',
            'sales.view', 'sales.record',
            'cnc_jobs.view', 'cnc_jobs.manage',
            'reports.operational',
        ],
        'accountant' => [
            'shops.view',
            'customers.view',
            'employees.view',
            'accounts.view', 'accounts.manage',
            'expense_categories.view', 'expense_categories.manage',
            'attendance.view',
            'employee_ledger.view', 'employee_payment.record',
            'salary.generate',
            'orders.view', 'orders.payment',
            'transactions.view', 'transactions.record',
            'expenses.view', 'expenses.record',
            'daily_closing.view', 'daily_closing.run',
            'suppliers.view', 'purchases.view',
            'supplier_ledger.view', 'supplier_payment.record',
            'sales.view',
            'reports.operational',
        ],
        'storekeeper' => [
            'shops.view',
            'materials.view', 'materials.manage',
            'purchases.view', 'purchases.record',
            'suppliers.view',
            'products.view', 'products.manage',
            'stock.view', 'stock.adjust',
            'orders.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::allPermissionNames() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (RoleEnum::cases() as $case) {
            $role = Role::firstOrCreate(['name' => $case->value, 'guard_name' => 'web']);

            $role->syncPermissions(
                $case === RoleEnum::Owner
                    ? self::allPermissionNames()
                    : self::ROLE_PERMISSIONS[$case->value]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    public static function allPermissionNames(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /**
     * Permissions no role but owner may ever hold.
     *
     * @return list<string>
     */
    public static function ownerOnlyPermissionNames(): array
    {
        return array_values(array_filter(
            self::allPermissionNames(),
            fn (string $name) => str_contains($name, 'profit') || str_contains($name, 'reports.financial')
        ));
    }
}
