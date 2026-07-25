import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { orderItemStatusLabels, orderStatusLabels, type OrderItemStatus, type OrderStatus } from '@/types/enums';
import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';

interface Item {
    id: number;
    item_name: string;
    category: string | null;
    wood_type: string | null;
    design_no: string | null;
    polish_type: string | null;
    dimensions: string | null;
    quantity: string;
    unit_price: string;
    line_total: string;
    status: OrderItemStatus;
    target_date: string | null;
    remarks: string | null;
}

interface StatusLog {
    id: number;
    from_status: string | null;
    to_status: string | null;
    changed_by: string | null;
    note: string | null;
    created_at: string | null;
}

interface Props {
    order: {
        id: number;
        order_no: string | null;
        status: OrderStatus;
        order_date: string;
        expected_delivery_date: string | null;
        delivered_at: string | null;
        subtotal: string;
        discount: string;
        delivery_charge: string;
        total_amount: string;
        paid_amount: string;
        due_amount: string;
        delivery_address: string | null;
        note: string | null;
        created_by: string | null;
        customer: { id: number; name: string; phone: string; area: string | null; address: string | null };
        shop: string;
        items: Item[];
        status_logs: StatusLog[];
    };
    canManage: boolean;
}

const statusTone: Record<OrderStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    confirmed: 'bg-blue-600/10 text-blue-700 dark:text-blue-400',
    in_production: 'bg-amber-600/10 text-amber-700 dark:text-amber-400',
    ready: 'bg-green-600/10 text-green-700 dark:text-green-400',
    delivered: 'bg-muted text-muted-foreground',
    cancelled: 'bg-destructive/10 text-destructive',
};

export default function ShowOrder({ order, canManage }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'অর্ডার', href: '/orders' },
        { title: order.order_no ?? 'খসড়া', href: '#' },
    ];

    const isOpen = order.status !== 'delivered' && order.status !== 'cancelled';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={order.order_no ?? 'খসড়া অর্ডার'} />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold">{order.order_no ?? 'খসড়া অর্ডার'}</h1>
                            <Badge className={cn('border-0', statusTone[order.status])}>{orderStatusLabels[order.status]}</Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {toBengaliDigits(order.order_date)} · {order.shop}
                        </p>
                    </div>

                    {canManage && isOpen && (
                        <Button variant="outline" asChild className="h-11">
                            <Link href={route('orders.edit', order.id)}>
                                <Pencil className="h-4 w-4" />
                                বদলান
                            </Link>
                        </Button>
                    )}
                </div>

                <FlashMessages />

                <section className="rounded-lg border p-4">
                    <h2 className="mb-2 font-medium">কাস্টমার</h2>
                    <Link href={route('customers.edit', order.customer.id)} className="hover:underline">
                        <p className="font-medium">{order.customer.name}</p>
                    </Link>
                    <p className="text-muted-foreground text-sm">
                        {toBengaliDigits(order.customer.phone)}
                        {order.customer.area && ` · ${order.customer.area}`}
                    </p>
                    {order.delivery_address && (
                        <p className="mt-2 text-sm">
                            <span className="text-muted-foreground">ডেলিভারি: </span>
                            {order.delivery_address}
                        </p>
                    )}
                </section>

                <section className="grid gap-3 rounded-lg border p-4 sm:grid-cols-3">
                    <div>
                        <p className="text-muted-foreground text-sm">সর্বমোট</p>
                        <p className="text-lg font-semibold">
                            <Money amount={order.total_amount} />
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">জমা</p>
                        <p className="text-lg font-semibold">
                            <Money amount={order.paid_amount} />
                        </p>
                    </div>
                    <div>
                        <p className="text-muted-foreground text-sm">বাকি</p>
                        <p className={cn('text-lg font-semibold', Number(order.due_amount) > 0 && 'text-destructive')}>
                            <Money amount={order.due_amount} />
                        </p>
                    </div>

                    <div className="text-muted-foreground border-t pt-3 text-sm sm:col-span-3">
                        <div className="flex justify-between">
                            <span>আইটেমের মোট</span>
                            <Money amount={order.subtotal} />
                        </div>
                        {Number(order.discount) > 0 && (
                            <div className="flex justify-between">
                                <span>ছাড়</span>
                                <span>− ৳ {toBengaliDigits(order.discount)}</span>
                            </div>
                        )}
                        {Number(order.delivery_charge) > 0 && (
                            <div className="flex justify-between">
                                <span>ডেলিভারি খরচ</span>
                                <Money amount={order.delivery_charge} />
                            </div>
                        )}
                        {order.expected_delivery_date && (
                            <div className="flex justify-between">
                                <span>ডেলিভারির তারিখ</span>
                                <span>{toBengaliDigits(order.expected_delivery_date)}</span>
                            </div>
                        )}
                    </div>
                </section>

                <section className="flex flex-col gap-2">
                    <h2 className="font-medium">আইটেম</h2>

                    {order.items.map((item) => (
                        <div key={item.id} className="rounded-lg border p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium">{item.item_name}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {[item.category, item.wood_type, item.dimensions, item.polish_type]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                                <Badge variant="outline" className="shrink-0">
                                    {orderItemStatusLabels[item.status]}
                                </Badge>
                            </div>

                            <div className="text-muted-foreground mt-2 flex flex-wrap justify-between gap-x-4 text-sm">
                                <span>
                                    {toBengaliDigits(item.quantity)} × ৳ {toBengaliDigits(item.unit_price)}
                                </span>
                                <Money amount={item.line_total} className="text-foreground font-medium" />
                            </div>

                            {item.remarks && <p className="text-muted-foreground mt-2 text-sm">{item.remarks}</p>}
                        </div>
                    ))}
                </section>

                {order.note && (
                    <section className="rounded-lg border p-4">
                        <h2 className="mb-1 font-medium">নোট</h2>
                        <p className="text-sm">{order.note}</p>
                    </section>
                )}

                {order.status_logs.length > 0 && (
                    <section className="rounded-lg border p-4">
                        <h2 className="mb-3 font-medium">অবস্থার ইতিহাস</h2>
                        <div className="flex flex-col gap-3">
                            {order.status_logs.map((log) => (
                                <div key={log.id} className="flex gap-3 text-sm">
                                    <div className="bg-muted-foreground/40 mt-1.5 h-2 w-2 shrink-0 rounded-full" />
                                    <div className="min-w-0">
                                        <p>
                                            {log.from_status && (
                                                <span className="text-muted-foreground">
                                                    {orderStatusLabels[log.from_status as OrderStatus]} →{' '}
                                                </span>
                                            )}
                                            <span className="font-medium">
                                                {log.to_status ? orderStatusLabels[log.to_status as OrderStatus] : '—'}
                                            </span>
                                        </p>
                                        <p className="text-muted-foreground">
                                            {log.created_at && toBengaliDigits(log.created_at)}
                                            {log.changed_by && ` · ${log.changed_by}`}
                                        </p>
                                        {log.note && <p className="text-muted-foreground">{log.note}</p>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
