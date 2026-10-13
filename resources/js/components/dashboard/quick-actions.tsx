import { usePermission } from '@/hooks/use-permission';
import { Link } from '@inertiajs/react';
import { Banknote, CalendarCheck, CalendarClock, ClipboardList, PackagePlus, type LucideIcon } from 'lucide-react';

interface Action {
    label: string;
    href: string;
    icon: LucideIcon;
    permission: string;
}

/**
 * The jobs people open the app to do, one tap away. A button shows only when
 * the reader may perform that action; the route checks it again.
 */
export function QuickActions() {
    const { can } = usePermission();

    const actions: Action[] = [
        { label: 'নতুন অর্ডার', href: route('orders.create'), icon: ClipboardList, permission: 'orders.manage' },
        { label: 'হাজিরা নিন', href: route('attendance.index'), icon: CalendarCheck, permission: 'attendance.mark' },
        { label: 'কেনাকাটা', href: route('purchases.create'), icon: PackagePlus, permission: 'purchases.record' },
        { label: 'খরচ', href: route('expenses.create'), icon: Banknote, permission: 'expenses.record' },
        { label: 'দিন শেষের হিসাব', href: route('daily-closing.index'), icon: CalendarClock, permission: 'daily_closing.run' },
    ].filter((action) => can(action.permission));

    if (actions.length === 0) {
        return null;
    }

    return (
        <section className="flex flex-col gap-2">
            <h2 className="text-lg font-semibold">আজ কী করবেন?</h2>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                {actions.map(({ label, href, icon: Icon }) => (
                    <Link
                        key={href}
                        href={href}
                        className="bg-primary text-primary-foreground hover:bg-primary/90 flex min-h-14 items-center gap-2 rounded-xl px-4 py-3 text-base font-medium transition-colors"
                    >
                        <Icon className="size-5 shrink-0" />
                        {label}
                    </Link>
                ))}
            </div>
        </section>
    );
}
