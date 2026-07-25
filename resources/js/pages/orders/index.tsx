import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { orderStatusLabels, type OrderStatus } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface OrderRow {
    id: number;
    order_no: string | null;
    status: OrderStatus;
    order_date: string;
    expected_delivery_date: string | null;
    total_amount: string;
    due_amount: string;
    customer: { name: string; phone: string };
}

interface Props {
    orders: Paginated<OrderRow>;
    search: string;
    status: string;
    statuses: { value: string; label: string }[];
    canManage: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'অর্ডার', href: '/orders' },
];

const statusTone: Record<OrderStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    confirmed: 'bg-blue-600/10 text-blue-700 dark:text-blue-400',
    in_production: 'bg-amber-600/10 text-amber-700 dark:text-amber-400',
    ready: 'bg-green-600/10 text-green-700 dark:text-green-400',
    delivered: 'bg-muted text-muted-foreground',
    cancelled: 'bg-destructive/10 text-destructive',
};

export default function OrdersIndex({ orders, search, status, statuses, canManage }: Props) {
    const [term, setTerm] = useState(search);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => reload({ search: term }), 300);

        return () => clearTimeout(timer);
    }, [term]);

    const reload = (params: { search?: string; status?: string }) => {
        router.get(
            route('orders.index'),
            {
                ...(params.search ?? term ? { search: params.search ?? term } : {}),
                ...(params.status !== undefined ? (params.status ? { status: params.status } : {}) : status ? { status } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const filters = [{ value: '', label: 'সব' }, { value: 'open', label: 'চলমান' }, ...statuses];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="অর্ডার" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">অর্ডার</h1>
                        <p className="text-muted-foreground text-sm">মোবাইল নম্বর বা অর্ডার নম্বর দিয়ে খুঁজুন</p>
                    </div>
                    {canManage && (
                        <Button asChild className="h-11 text-base">
                            <Link href={route('orders.create')}>
                                <Plus className="h-4 w-4" />
                                নতুন অর্ডার
                            </Link>
                        </Button>
                    )}
                </div>

                <FlashMessages />

                <div className="relative">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="01XXXXXXXXX বা SH-2607-0142"
                        inputMode="numeric"
                        className="h-12 pr-9 text-base"
                    />
                </div>

                <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                    {filters.map((filter) => (
                        <button
                            key={filter.value || 'all'}
                            type="button"
                            onClick={() => reload({ status: filter.value })}
                            className={cn(
                                'shrink-0 rounded-full border px-4 py-2 text-sm whitespace-nowrap transition-colors',
                                status === filter.value ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted',
                            )}
                        >
                            {filter.label}
                        </button>
                    ))}
                </div>

                {orders.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        {search ? `"${search}" এর জন্য কিছু পাওয়া যায়নি` : 'এখনো কোনো অর্ডার নেই'}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {orders.data.map((order) => (
                            <Link
                                key={order.id}
                                href={route('orders.show', order.id)}
                                className="hover:bg-muted/50 flex flex-col gap-2 rounded-lg border p-4 transition-colors"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium">{order.customer.name}</p>
                                        <p className="text-muted-foreground text-sm">{toBengaliDigits(order.customer.phone)}</p>
                                    </div>
                                    <Badge className={cn('shrink-0 border-0', statusTone[order.status])}>
                                        {orderStatusLabels[order.status]}
                                    </Badge>
                                </div>

                                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 text-sm">
                                    <span className="text-muted-foreground">
                                        {order.order_no ?? 'নম্বর দেওয়া হয়নি'} · {toBengaliDigits(order.order_date)}
                                    </span>
                                    <span className="flex gap-3">
                                        <Money amount={order.total_amount} className="font-medium" />
                                        {Number(order.due_amount) > 0 && (
                                            <span className="text-destructive">
                                                বাকি <Money amount={order.due_amount} />
                                            </span>
                                        )}
                                    </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}

                {orders.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground text-sm">
                            {toBengaliDigits(orders.from ?? 0)}–{toBengaliDigits(orders.to ?? 0)} / {toBengaliDigits(orders.total)}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" asChild disabled={orders.current_page === 1}>
                                <Link href={orders.links.find((l) => l.label === String(orders.current_page - 1))?.url ?? '#'} preserveScroll>
                                    আগের
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild disabled={orders.current_page === orders.last_page}>
                                <Link href={orders.links.find((l) => l.label === String(orders.current_page + 1))?.url ?? '#'} preserveScroll>
                                    পরের
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
