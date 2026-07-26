import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import {
    cashPaymentMethodLabels,
    materialUnitLabels,
    purchasePaymentTypeLabels,
    purchaseStatusLabels,
    type CashPaymentMethod,
    type MaterialUnit,
    type PurchasePaymentType,
    type PurchaseStatus,
} from '@/types/enums';
import { Head, Link } from '@inertiajs/react';
import { HandCoins } from 'lucide-react';

interface Line {
    id: number;
    name: string;
    item_type: string;
    quantity: string;
    unit: string | null;
    unit_price: string;
    line_total: string;
    note: string | null;
}

interface PaymentRow {
    id: number;
    allocated_amount: string;
    payment_date: string;
    payment_total: string;
    payment_method: CashPaymentMethod;
    reference_no: string | null;
    account: string | null;
}

interface Props {
    purchase: {
        id: number;
        purchase_no: string;
        reference_no: string | null;
        purchase_date: string;
        payment_due_date: string | null;
        payment_type: PurchasePaymentType;
        status: PurchaseStatus;
        subtotal: string;
        transport_cost: string;
        discount: string;
        total_amount: string;
        paid_amount: string;
        due_amount: string;
        note: string | null;
        shop: string | null;
        created_by: string | null;
        supplier: { id: number; name: string; business_name: string | null; phone: string | null };
    };
    items: Line[];
    payments: PaymentRow[];
}

const statusVariant: Record<PurchaseStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    pending: 'destructive',
    partial: 'secondary',
    paid: 'outline',
    returned: 'outline',
};

export default function PurchaseShow({ purchase, items, payments }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'কেনাকাটা', href: '/purchases' },
        { title: purchase.purchase_no, href: '#' },
    ];

    const unitLabel = (unit: string | null) => (unit ? (materialUnitLabels[unit as MaterialUnit] ?? unit) : '');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={purchase.purchase_no} />

            <div className="flex max-w-3xl flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-semibold">{toBengaliDigits(purchase.purchase_no)}</h1>
                        <p className="text-muted-foreground text-sm">
                            {toBengaliDigits(purchase.purchase_date)}
                            {purchase.reference_no && ` — চালান ${toBengaliDigits(purchase.reference_no)}`}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Badge variant="outline">{purchasePaymentTypeLabels[purchase.payment_type]}</Badge>
                        <Badge variant={statusVariant[purchase.status]}>{purchaseStatusLabels[purchase.status]}</Badge>
                    </div>
                </div>

                <FlashMessages />

                <Link
                    href={route('supplier-ledger.show', purchase.supplier.id)}
                    className="hover:bg-muted/50 rounded-lg border p-4 transition-colors"
                >
                    <p className="text-muted-foreground text-sm">সরবরাহকারী</p>
                    <p className="font-medium">{purchase.supplier.business_name ?? purchase.supplier.name}</p>
                    {purchase.supplier.phone && (
                        <p className="text-muted-foreground text-sm">{toBengaliDigits(purchase.supplier.phone)}</p>
                    )}
                </Link>

                <div className="grid grid-cols-2 gap-2">
                    <div className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">মোট</p>
                        <p className="text-xl font-semibold">
                            <Money amount={purchase.total_amount} />
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">বাকি</p>
                        <p className={`text-xl font-semibold ${Number(purchase.due_amount) > 0 ? 'text-destructive' : ''}`}>
                            <Money amount={purchase.due_amount} />
                        </p>
                        {purchase.payment_due_date && Number(purchase.due_amount) > 0 && (
                            <p className="text-muted-foreground text-xs">
                                শোধের তারিখ {toBengaliDigits(purchase.payment_due_date)}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex flex-col gap-2">
                    <p className="font-medium">যা এসেছে</p>
                    {items.map((line) => (
                        <div key={line.id} className="flex items-center gap-3 rounded-lg border p-3">
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium">{line.name}</p>
                                <p className="text-muted-foreground text-sm">
                                    {toBengaliDigits(line.quantity)} {unitLabel(line.unit)} × ৳{' '}
                                    {toBengaliDigits(line.unit_price)}
                                </p>
                                {line.note && <p className="text-muted-foreground truncate text-sm">{line.note}</p>}
                            </div>
                            <Money amount={line.line_total} className="font-semibold" />
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-1 rounded-lg border p-4 text-sm">
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">মালামালের দাম</span>
                        <Money amount={purchase.subtotal} />
                    </div>
                    {Number(purchase.transport_cost) > 0 && (
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">পরিবহন খরচ</span>
                            <Money amount={purchase.transport_cost} />
                        </div>
                    )}
                    {Number(purchase.discount) > 0 && (
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">ছাড়</span>
                            <span>
                                − <Money amount={purchase.discount} />
                            </span>
                        </div>
                    )}
                    <div className="mt-1 flex justify-between border-t pt-2 font-semibold">
                        <span>মোট</span>
                        <Money amount={purchase.total_amount} />
                    </div>
                    <div className="flex justify-between">
                        <span className="text-muted-foreground">পরিশোধিত</span>
                        <Money amount={purchase.paid_amount} />
                    </div>
                </div>

                <div className="flex flex-col gap-2">
                    <p className="font-medium">পরিশোধের হিসাব</p>

                    {payments.length === 0 ? (
                        <div className="text-muted-foreground rounded-lg border border-dashed p-6 text-center text-sm">
                            {/* A credit challan starts here and stays here until someone pays. */}
                            এখনো কোনো টাকা দেওয়া হয়নি
                        </div>
                    ) : (
                        payments.map((payment) => (
                            <div key={payment.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <HandCoins className="text-muted-foreground h-4 w-4 shrink-0" />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium">
                                        {toBengaliDigits(payment.payment_date)} — {cashPaymentMethodLabels[payment.payment_method]}
                                    </p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {payment.account ?? '—'}
                                        {payment.reference_no && ` — ${toBengaliDigits(payment.reference_no)}`}
                                        {/* One handover can settle several challans, so say what landed here. */}
                                        {payment.payment_total !== payment.allocated_amount &&
                                            ` — মোট ৳ ${toBengaliDigits(payment.payment_total)} এর মধ্যে`}
                                    </p>
                                </div>
                                <Money amount={payment.allocated_amount} className="font-semibold" />
                            </div>
                        ))
                    )}
                </div>

                {(purchase.note || purchase.shop || purchase.created_by) && (
                    <div className="text-muted-foreground flex flex-col gap-1 rounded-lg border p-4 text-sm">
                        {purchase.note && <p>{purchase.note}</p>}
                        {purchase.shop && <p>দোকান: {purchase.shop}</p>}
                        {purchase.created_by && <p>লিখেছেন: {purchase.created_by}</p>}
                    </div>
                )}

                <Button variant="outline" asChild className="h-11">
                    <Link href={route('purchases.index')}>চালানের তালিকায় ফিরুন</Link>
                </Button>
            </div>
        </AppLayout>
    );
}
