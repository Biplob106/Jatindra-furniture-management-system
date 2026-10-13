import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { type NavGroup, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

/**
 * An item is active on its own page and on every page beneath it, so
 * /orders/5/edit lights up অর্ডার. The query string is ignored; /orders-x is not
 * beneath /orders.
 */
export function isActiveUrl(itemUrl: string, pageUrl: string): boolean {
    const path = pageUrl.split('?')[0];

    return path === itemUrl || path.startsWith(`${itemUrl}/`);
}

// Folded state is a per-browser convenience. Storage can be blocked or
// cleared, so every access is guarded and the menu works without it.
function readOpen(key: string): boolean | null {
    try {
        const value = window.localStorage.getItem(key);

        return value === null ? null : value === '1';
    } catch {
        return null;
    }
}

function writeOpen(key: string, open: boolean): void {
    try {
        window.localStorage.setItem(key, open ? '1' : '0');
    } catch {
        // Storage blocked; the group still toggles for this page view.
    }
}

function Items({ items, pageUrl }: { items: NavItem[]; pageUrl: string }) {
    return (
        <SidebarMenu>
            {items.map((item) => (
                <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton asChild isActive={isActiveUrl(item.url, pageUrl)} className="h-10">
                        <Link href={item.url} prefetch>
                            {item.icon && <item.icon />}
                            <span>{item.title}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            ))}
        </SidebarMenu>
    );
}

function FoldedGroup({ group, pageUrl }: { group: NavGroup; pageUrl: string }) {
    const storageKey = `sidebar-group:${group.title}`;
    const holdsCurrentPage = group.items.some((item) => isActiveUrl(item.url, pageUrl));
    const [open, setOpen] = useState(() => holdsCurrentPage || (readOpen(storageKey) ?? false));

    const toggle = (next: boolean) => {
        setOpen(next);
        writeOpen(storageKey, next);
    };

    return (
        <Collapsible open={open} onOpenChange={toggle} asChild>
            <SidebarGroup className="px-2 py-0">
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="flex h-10 w-full items-center">
                        {group.title}
                        <ChevronRight className={cn('ml-auto size-4 transition-transform', open && 'rotate-90')} />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <Items items={group.items} pageUrl={pageUrl} />
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { url } = usePage();

    return (
        <>
            {groups.map((group) =>
                group.collapsible ? (
                    <FoldedGroup key={group.title} group={group} pageUrl={url} />
                ) : (
                    <SidebarGroup key={group.title} className="px-2 py-0">
                        <SidebarGroupLabel>{group.title}</SidebarGroupLabel>
                        <Items items={group.items} pageUrl={url} />
                    </SidebarGroup>
                ),
            )}
        </>
    );
}
