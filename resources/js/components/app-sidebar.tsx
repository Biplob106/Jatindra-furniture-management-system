import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    Banknote,
    CalendarCheck,
    CalendarClock,
    ClipboardList,
    Hammer,
    HandCoins,
    HardHat,
    LayoutGrid,
    Receipt,
    Shapes,
    Store,
    UserRound,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from './app-logo';

/**
 * Nav entries carry the permission that guards their route. Hiding an item is
 * a convenience; the route enforces the same permission server-side.
 */
const navItems: (NavItem & { permission?: string })[] = [
    {
        title: 'ড্যাশবোর্ড',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'অর্ডার',
        url: '/orders',
        icon: ClipboardList,
        permission: 'orders.view',
    },
    {
        title: 'হাজিরা',
        url: '/attendance',
        icon: CalendarCheck,
        permission: 'attendance.view',
    },
    {
        title: 'দিনের হিসাব',
        url: '/daily-closing',
        icon: CalendarClock,
        permission: 'daily_closing.view',
    },
    {
        title: 'খরচ',
        url: '/expenses',
        icon: Banknote,
        permission: 'expenses.view',
    },
    {
        title: 'কর্মীর হিসাব',
        url: '/employee-ledger',
        icon: HandCoins,
        permission: 'employee_ledger.view',
    },
    {
        title: 'কাস্টমার',
        url: '/customers',
        icon: UserRound,
        permission: 'customers.view',
    },
    {
        title: 'কর্মী',
        url: '/employees',
        icon: HardHat,
        permission: 'employees.view',
    },
    {
        title: 'দোকান',
        url: '/shops',
        icon: Store,
        permission: 'shops.view',
    },
    {
        title: 'কাজের ধরন',
        url: '/trades',
        icon: Hammer,
        permission: 'trades.view',
    },
    {
        title: 'হিসাব',
        url: '/accounts',
        icon: Wallet,
        permission: 'accounts.view',
    },
    {
        title: 'খরচের খাত',
        url: '/expense-categories',
        icon: Receipt,
        permission: 'expense_categories.view',
    },
    {
        title: 'পণ্যের ক্যাটাগরি',
        url: '/product-categories',
        icon: Shapes,
        permission: 'product_categories.view',
    },
    {
        title: 'ব্যবহারকারী',
        url: '/users',
        icon: Users,
        permission: 'users.manage',
    },
];

export function AppSidebar() {
    const { can } = usePermission();

    const mainNavItems = navItems.filter((item) => !item.permission || can(item.permission));

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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
