import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavGroup, type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    Armchair,
    Banknote,
    Boxes,
    CalendarCheck,
    CalendarClock,
    ClipboardList,
    Coins,
    FileText,
    Hammer,
    HandCoins,
    HardHat,
    LayoutGrid,
    PackagePlus,
    PieChart,
    Receipt,
    Scissors,
    Shapes,
    Store,
    Truck,
    UserRound,
    Users,
    Warehouse,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';
type GuardedItem = NavItem & { permission?: string };

/**
 * Nav entries carry the permission that guards their route. Hiding an item is
 * a convenience; the route enforces the same permission server-side.
 *
 * Daily work comes first and is always open. Setup is touched rarely, so it
 * folds away.
 */
const navGroups: { title: string; collapsible?: boolean; items: GuardedItem[] }[] = [
    {
        title: 'দৈনিক কাজ',
        items: [
            { title: 'ড্যাশবোর্ড', url: '/dashboard', icon: LayoutGrid },
            { title: 'অর্ডার', url: '/orders', icon: ClipboardList, permission: 'orders.view' },
            { title: 'হাজিরা', url: '/attendance', icon: CalendarCheck, permission: 'attendance.view' },
            { title: 'কেনাকাটা', url: '/purchases', icon: PackagePlus, permission: 'purchases.view' },
            { title: 'খরচ', url: '/expenses', icon: Banknote, permission: 'expenses.view' },
            { title: 'দিন শেষের হিসাব', url: '/daily-closing', icon: CalendarClock, permission: 'daily_closing.view' },
        ],
    },
    {
        title: 'পাওনা-দেনা',
        items: [
            { title: 'কর্মীর পাওনা', url: '/employee-ledger', icon: HandCoins, permission: 'employee_ledger.view' },
            { title: 'সরবরাহকারীর পাওনা', url: '/supplier-ledger', icon: Coins, permission: 'supplier_ledger.view' },
            { title: 'পে-রোল রিপোর্ট', url: '/reports/payroll', icon: FileText, permission: 'reports.operational' },
            { title: 'খরচের রিপোর্ট', url: '/reports/spending', icon: PieChart, permission: 'reports.financial' },
        ],
    },
    {
        title: 'মালামাল ও পণ্য',
        items: [
            { title: 'মালামালের মজুদ', url: '/stock', icon: Warehouse, permission: 'stock.view' },
            { title: 'মালামালের তালিকা', url: '/materials', icon: Boxes, permission: 'materials.view' },
            { title: 'পণ্য', url: '/products', icon: Armchair, permission: 'products.view' },
        ],
    },
    {
        title: 'সেটআপ',
        collapsible: true,
        items: [
            { title: 'কাস্টমার', url: '/customers', icon: UserRound, permission: 'customers.view' },
            { title: 'সরবরাহকারী', url: '/suppliers', icon: Truck, permission: 'suppliers.view' },
            { title: 'কর্মী', url: '/employees', icon: HardHat, permission: 'employees.view' },
            { title: 'দোকান', url: '/shops', icon: Store, permission: 'shops.view' },
            { title: 'কাজের ধরন', url: '/trades', icon: Hammer, permission: 'trades.view' },
            { title: 'ক্যাশ ও ব্যাংক', url: '/accounts', icon: Wallet, permission: 'accounts.view' },
            { title: 'খরচের খাত', url: '/expense-categories', icon: Receipt, permission: 'expense_categories.view' },
            { title: 'কর্তনের খাত', url: '/deduction-categories', icon: Scissors, permission: 'deduction_categories.view' },
            { title: 'পণ্যের ক্যাটাগরি', url: '/product-categories', icon: Shapes, permission: 'product_categories.view' },
            { title: 'ব্যবহারকারী', url: '/users', icon: Users, permission: 'users.manage' },
        ],
    },
];

export function AppSidebar() {
    const { can } = usePermission();

    // A group whose every item is hidden is not rendered at all.
    const groups: NavGroup[] = navGroups
        .map((group) => ({ ...group, items: group.items.filter((item) => !item.permission || can(item.permission)) }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
