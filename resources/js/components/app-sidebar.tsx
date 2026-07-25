import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermission } from '@/hooks/use-permission';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { LayoutGrid, Users } from 'lucide-react';
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
