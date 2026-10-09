import { DataTable, toBengaliDigits, type Column } from '@/components/data-table';
import { Option } from '@/components/form-field';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';

type SpendingKind = 'purchase' | 'expense';

interface SpendingRow {
    kind: SpendingKind;
    id: number;
    entry_date: string;
    reference: string | null;
    heading: string | null;
    party: string | null;
    amount: string;
    due: string;
}

interface Heading {
    kind: SpendingKind;
    name: string;
    total: string;
}

interface Props {
    totals: { purchases: string; purchases_due: string; expenses: string; total: string };
    byHeading: Heading[];
    rows: Paginated<SpendingRow>;
    month: string;
    shopId: number | null;
    supplierId: number | null;
    search: string;
    shops: Option[];
    suppliers: Option[];
}

const kindLabels: Record<SpendingKind, string> = {
    purchase: 'ক্রয়',
    expense: 'খরচ',
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'সব খরচের হিসাব', href: '/reports/spending' },
];

const ALL = '__all__';

export default function SpendingReport({ totals, byHeading, rows, month, shopId, supplierId, search, shops, suppliers }: Props) {
    const filters: Record<string, string | number> = {
        month,
        ...(shopId ? { shop_id: shopId } : {}),
        ...(supplierId ? { supplier_id: supplierId } : {}),
    };

    const reload = (changes: Record<string, string>) => {
        const next: Record<string, string | number> = { ...filters, ...(search ? { search } : {}), ...changes };

        for (const key of Object.keys(next)) {
            if (next[key] === '') delete next[key];
        }

        router.get(route('reports.spending'), next, { preserveState: true, preserveScroll: true, replace: true });
    };

    // Display only: the widest bar is the biggest heading.
    const largest = Math.max(...byHeading.map((row) => Number(row.total)), 1);

    const columns: Column<SpendingRow>[] = [
        {
            header: 'তারিখ',
            cell: (row) => <span className="whitespace-nowrap">{toBengaliDigits(row.entry_date)}</span>,
        },
        {
            header: 'কী',
            cell: (row) => (
                <div className="flex flex-col gap-1">
                    <Badge variant={row.kind === 'purchase' ? 'default' : 'secondary'} className="w-fit">
                        {kindLabels[row.kind]}
                    </Badge>
                    {row.kind === 'purchase' ? (
                        <Link href={route('purchases.show', row.id)} className="text-sm underline">
                            {row.reference}
                        </Link>
                    ) : (
                        <span className="text-sm">{row.heading}</span>
                    )}
                </div>
            ),
        },
        {
            header: 'কাকে',
            cell: (row) => row.party ?? <span className="text-muted-foreground">—</span>,
            hideOnMobile: true,
        },
        {
            header: 'টাকা',
            className: 'text-right',
            cell: (row) => (
                <div className="flex flex-col items-end">
                    <Money amount={row.amount} className="font-semibold" />
                    {Number(row.due) > 0 && (
                        <span className="text-destructive text-xs">
                            বাকি <Money amount={row.due} />
                        </span>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="সব খরচের হিসাব" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">সব খরচের হিসাব</h1>
                    <p className="text-muted-foreground text-sm">ক্রয় আর দোকানের খরচ একসাথে। বাকিতে কেনা মালও যে মাসে এসেছে সেই মাসে ধরা হয়েছে।</p>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="month">মাস</Label>
                        <Input
                            id="month"
                            type="month"
                            value={month}
                            onChange={(e) => e.target.value && reload({ month: e.target.value })}
                            className="h-12 text-base"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="shop">দোকান</Label>
                        <Select value={shopId ? String(shopId) : ALL} onValueChange={(value) => reload({ shop_id: value === ALL ? '' : value })}>
                            <SelectTrigger id="shop" className="h-12 text-base">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>সব দোকান</SelectItem>
                                {shops.map((shop) => (
                                    <SelectItem key={shop.value} value={String(shop.value)}>
                                        {shop.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="supplier">সরবরাহকারী</Label>
                        <Select
                            value={supplierId ? String(supplierId) : ALL}
                            onValueChange={(value) => reload({ supplier_id: value === ALL ? '' : value })}
                        >
                            <SelectTrigger id="supplier" className="h-12 text-base">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>সব সরবরাহকারী</SelectItem>
                                {suppliers.map((supplier) => (
                                    <SelectItem key={supplier.value} value={String(supplier.value)}>
                                        {supplier.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {supplierId && <p className="text-muted-foreground text-sm">সরবরাহকারী বাছাই করলে শুধু তার ক্রয় দেখায়, দোকানের খরচ নয়।</p>}

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">এই মাসের মোট খরচ</p>
                        <p className="text-2xl font-semibold">
                            <Money amount={totals.total} />
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">ক্রয়</p>
                        <p className="text-xl font-semibold">
                            <Money amount={totals.purchases} />
                        </p>
                        {Number(totals.purchases_due) > 0 && (
                            <p className="text-destructive text-sm">
                                এখনো বাকি <Money amount={totals.purchases_due} />
                            </p>
                        )}
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-muted-foreground text-sm">দোকানের খরচ</p>
                        <p className="text-xl font-semibold">
                            <Money amount={totals.expenses} />
                        </p>
                    </div>
                </div>

                {byHeading.length > 0 && (
                    <div className="rounded-lg border p-4">
                        <p className="mb-3 font-medium">টাকা কোথায় গেল</p>
                        <div className="flex flex-col gap-3">
                            {byHeading.map((row) => (
                                <div key={`${row.kind}-${row.name}`} className="flex flex-col gap-1">
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <span className="flex items-center gap-2">
                                            <Badge variant={row.kind === 'purchase' ? 'default' : 'secondary'}>{kindLabels[row.kind]}</Badge>
                                            {row.name}
                                        </span>
                                        <Money amount={row.total} signed />
                                    </div>
                                    {Number(row.total) > 0 && (
                                        <div className="bg-muted h-1.5 rounded-full">
                                            <div
                                                className={row.kind === 'purchase' ? 'bg-primary h-1.5 rounded-full' : 'h-1.5 rounded-full bg-amber-500'}
                                                style={{ width: `${(Number(row.total) / largest) * 100}%` }}
                                            />
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <DataTable
                    rows={rows}
                    columns={columns}
                    routeName="reports.spending"
                    params={filters}
                    search={search}
                    searchPlaceholder="সরবরাহকারী, খাত বা চালান নম্বর"
                    emptyMessage="এই মাসে কোনো ক্রয় বা খরচ নেই"
                    rowKey={(row) => `${row.kind}-${row.id}`}
                />
            </div>
        </AppLayout>
    );
}
