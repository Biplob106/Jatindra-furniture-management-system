import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Option, SelectField, TextField } from '@/components/form-field';
import {
    cashPaymentMethodLabels,
    orderItemStatusLabels,
    orderStatusLabels,
    type CashPaymentMethod,
    type OrderItemStatus,
    type OrderStatus,
} from '@/types/enums';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ItemWorks, type Work, type WorkerOption } from './item-works';
import { LoaderCircle, Pencil, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

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
    works: Work[];
}

interface StatusLog {
    id: number;
    from_status: string | null;
    to_status: string | null;
    changed_by: string | null;
    note: string | null;
    created_at: string | null;
}

interface Payment {
    id: number;
    txn_date: string;
    amount: string;
    direction: 'in' | 'out';
    payment_method: CashPaymentMethod;
    note: string | null;
}

interface Props {
    nextStatuses: { value: OrderStatus; label: string }[];
    accounts: Option[];
    paymentMethods: Option[];
    workers: WorkerOption[];
    workStatuses: Option[];
    today: string;
    canTakePayment: boolean;
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
        payments: Payment[];
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

export default function ShowOrder({
    order,
    nextStatuses,
    accounts,
    paymentMethods,
    workers,
    workStatuses,
    today,
    canManage,
    canTakePayment,
}: Props) {
    const [showPayment, setShowPayment] = useState(false);
    const [movingStatus, setMovingStatus] = useState(false);

    const payment = useForm({
        amount: '',
        account_id: accounts.length === 1 ? String(accounts[0].value) : '',
        paid_on: today,
        payment_method: 'cash',
        note: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'অর্ডার', href: '/orders' },
        { title: order.order_no ?? 'খসড়া', href: '#' },
    ];

    const isOpen = order.status !== 'delivered' && order.status !== 'cancelled';
    const owes = Number(order.due_amount) > 0;

    const moveTo = (next: OrderStatus) => {
        router.post(
            route('orders.status', order.id),
            { status: next },
            {
                preserveScroll: true,
                onStart: () => setMovingStatus(true),
                onFinish: () => setMovingStatus(false),
            },
        );
    };

    const submitPayment: FormEventHandler = (e) => {
        e.preventDefault();

        payment.post(route('orders.payments.store', order.id), {
            preserveScroll: true,
            onSuccess: () => {
                payment.reset('amount', 'note');
                setShowPayment(false);
            },
        });
    };

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

                {canManage && nextStatuses.length > 0 && (
                    <section className="flex flex-col gap-3 rounded-lg border p-4">
                        <h2 className="font-medium">পরবর্তী ধাপ</h2>
                        <div className="flex flex-wrap gap-2">
                            {nextStatuses.map((next) => (
                                <Button
                                    key={next.value}
                                    type="button"
                                    variant={next.value === 'cancelled' ? 'outline' : 'default'}
                                    className={cn('h-12 text-base', next.value === 'cancelled' && 'text-destructive')}
                                    disabled={movingStatus}
                                    onClick={() => {
                                        if (next.value !== 'cancelled' || window.confirm('এই অর্ডার বাতিল করতে চান?')) {
                                            moveTo(next.value);
                                        }
                                    }}
                                >
                                    {movingStatus && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    {next.label}
                                </Button>
                            ))}
                        </div>
                        {order.status === 'draft' && (
                            <p className="text-muted-foreground text-sm">নিশ্চিত করলে অর্ডার নম্বর দেওয়া হবে।</p>
                        )}
                    </section>
                )}

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

                {/* Payments */}
                <section className="flex flex-col gap-3 rounded-lg border p-4">
                    <div className="flex items-center justify-between gap-3">
                        <h2 className="font-medium">জমা</h2>
                        {canTakePayment && owes && !showPayment && (
                            <Button type="button" variant="outline" className="h-11" onClick={() => setShowPayment(true)}>
                                <Plus className="h-4 w-4" />
                                টাকা জমা নিন
                            </Button>
                        )}
                    </div>

                    {showPayment && (
                        <form onSubmit={submitPayment} className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                            <TextField
                                id="amount"
                                label="টাকার পরিমাণ"
                                type="number"
                                numeric
                                value={payment.data.amount}
                                onChange={(value) => payment.setData('amount', value)}
                                error={payment.errors.amount}
                                required
                                autoFocus
                                hint={`বাকি ৳ ${toBengaliDigits(order.due_amount)}`}
                            />

                            <SelectField
                                id="account_id"
                                label="কোন হিসাবে"
                                value={payment.data.account_id}
                                onChange={(value) => payment.setData('account_id', value)}
                                options={accounts}
                                error={payment.errors.account_id}
                                required
                            />

                            <SelectField
                                id="payment_method"
                                label="কীভাবে"
                                value={payment.data.payment_method}
                                onChange={(value) => payment.setData('payment_method', value)}
                                options={paymentMethods}
                                error={payment.errors.payment_method}
                            />

                            <TextField
                                id="paid_on"
                                label="তারিখ"
                                type="date"
                                value={payment.data.paid_on}
                                onChange={(value) => payment.setData('paid_on', value)}
                                error={payment.errors.paid_on}
                                required
                            />

                            <div className="sm:col-span-2">
                                <TextField
                                    id="payment_note"
                                    label="নোট"
                                    value={payment.data.note}
                                    onChange={(value) => payment.setData('note', value)}
                                    error={payment.errors.note}
                                />
                            </div>

                            <div className="flex gap-3 sm:col-span-2">
                                <Button type="submit" className="h-12 flex-1 text-base" disabled={payment.processing}>
                                    {payment.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    জমা নিন
                                </Button>
                                <Button type="button" variant="outline" className="h-12 text-base" onClick={() => setShowPayment(false)}>
                                    বাতিল
                                </Button>
                            </div>
                        </form>
                    )}

                    {order.payments.length === 0 ? (
                        <p className="text-muted-foreground text-sm">এখনো কোনো টাকা জমা হয়নি।</p>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {order.payments.map((entry) => (
                                <div key={entry.id} className="flex items-center justify-between gap-3 border-b pb-2 text-sm last:border-0">
                                    <div>
                                        <p className="font-medium">{cashPaymentMethodLabels[entry.payment_method]}</p>
                                        <p className="text-muted-foreground">
                                            {toBengaliDigits(entry.txn_date)}
                                            {entry.note && ` · ${entry.note}`}
                                        </p>
                                    </div>
                                    <span
                                        className={cn(
                                            'font-semibold tabular-nums',
                                            entry.direction === 'in' ? 'text-green-700 dark:text-green-400' : 'text-destructive',
                                        )}
                                    >
                                        {entry.direction === 'in' ? '+' : '−'} ৳ {toBengaliDigits(entry.amount)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
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

                            {canManage && (
                                <ItemWorks
                                    itemId={item.id}
                                    works={item.works}
                                    workers={workers}
                                    workStatuses={workStatuses}
                                    today={today}
                                />
                            )}
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
