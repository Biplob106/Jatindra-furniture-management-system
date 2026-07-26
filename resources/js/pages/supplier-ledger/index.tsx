import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface PayableRow {
    supplier_id: number;
    name: string;
    business_name: string | null;
    phone: string | null;
    due_total: string;
    challan_count: number;
    oldest_date: string;
    oldest_age: number;
    current: string;
    days31: string;
    days61: string;
    days90plus: string;
}

interface Totals {
    current: string;
    days31: string;
    days61: string;
    days90plus: string;
    total: string;
}

interface Props {
    suppliers: PayableRow[];
    search: string;
    totals: Totals;
    asOf: string;
    canPay: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'সরবরাহকারীর হিসাব', href: '/supplier-ledger' },
];

/** Half-open buckets, so every challan lands in exactly one. */
const buckets: { key: keyof Totals; label: string; urgent?: boolean }[] = [
    { key: 'current', label: '০-৩০ দিন' },
    { key: 'days31', label: '৩১-৬০ দিন' },
    { key: 'days61', label: '৬১-৯০ দিন' },
    { key: 'days90plus', label: '৯০+ দিন', urgent: true },
];

export default function SupplierLedgerIndex({ suppliers, search, totals }: Props) {
    const [term, setTerm] = useState(search);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(route('supplier-ledger.index'), term ? { search: term } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [term]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="সরবরাহকারীর হিসাব" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">সরবরাহকারীর হিসাব</h1>
                    <p className="text-muted-foreground text-sm">কার কত বাকি, আর কত দিন ধরে</p>
                </div>

                <FlashMessages />

                <div className="rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">মোট বাকি</p>
                    <p className="text-2xl font-semibold">
                        <Money amount={totals.total} />
                    </p>
                </div>

                {/* Age counted from the challan date: how long the money has sat with us. */}
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    {buckets.map((bucket) => (
                        <div
                            key={bucket.key}
                            className={
                                bucket.urgent && Number(totals[bucket.key]) > 0
                                    ? 'border-destructive/40 bg-destructive/5 rounded-lg border p-3'
                                    : 'rounded-lg border p-3'
                            }
                        >
                            <p className="text-muted-foreground text-xs">{bucket.label}</p>
                            <p className="font-semibold">
                                <Money amount={totals[bucket.key]} />
                            </p>
                        </div>
                    ))}
                </div>

                <div className="relative sm:max-w-xs">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="নাম, দোকান বা মোবাইল"
                        className="h-11 pr-9 text-base"
                    />
                </div>

                {suppliers.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        {search ? `"${search}" এর জন্য কিছু পাওয়া যায়নি` : 'কারো কাছে কোনো বাকি নেই'}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {suppliers.map((supplier) => (
                            <Link
                                key={supplier.supplier_id}
                                href={route('supplier-ledger.show', supplier.supplier_id)}
                                className="hover:bg-muted/50 flex items-center gap-3 rounded-lg border p-4 transition-colors"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{supplier.business_name ?? supplier.name}</p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {toBengaliDigits(supplier.challan_count)} টি চালান, সবচেয়ে পুরোনো{' '}
                                        {toBengaliDigits(supplier.oldest_age)} দিন
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Money amount={supplier.due_total} className="font-semibold" />
                                    {Number(supplier.days90plus) > 0 && (
                                        <p className="text-destructive text-xs">
                                            ৯০+ দিনে <Money amount={supplier.days90plus} />
                                        </p>
                                    )}
                                </div>
                                <ChevronLeft className="text-muted-foreground h-4 w-4 shrink-0" />
                            </Link>
                        ))}
                    </div>
                )}

                <p className="text-muted-foreground text-sm">মোট {toBengaliDigits(suppliers.length)} জন সরবরাহকারী</p>
            </div>
        </AppLayout>
    );
}
