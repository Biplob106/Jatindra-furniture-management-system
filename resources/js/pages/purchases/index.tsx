import { Column, DataTable, toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { purchasePaymentTypeLabels, purchaseStatusLabels, type PurchasePaymentType, type PurchaseStatus } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface PurchaseRow {
    id: number;
    purchase_no: string;
    reference_no: string | null;
    purchase_date: string;
    payment_due_date: string | null;
    payment_type: PurchasePaymentType;
    status: PurchaseStatus;
    total_amount: string;
    due_amount: string;
    supplier: { id: number; name: string; business_name: string | null };
}

interface Props {
    purchases: Paginated<PurchaseRow>;
    search: string;
    status: string;
    statuses: Option[];
    today: string;
    canRecord: boolean;
}

const statusVariant: Record<PurchaseStatus, 'default' | 'secondary' | 'outline' | 'destructive'> = {
    pending: 'destructive',
    partial: 'secondary',
    paid: 'outline',
    returned: 'outline',
};

export default function PurchasesIndex({ purchases, search, status, statuses, today, canRecord }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'কেনাকাটা', href: '/purchases' },
    ];

    /** Past its agreed terms and still owed. */
    const isOverdue = (purchase: PurchaseRow) =>
        purchase.payment_due_date !== null && purchase.payment_due_date < today && Number(purchase.due_amount) > 0;

    const columns: Column<PurchaseRow>[] = [
        {
            header: 'চালান',
            cell: (purchase) => (
                <Link href={route('purchases.show', purchase.id)} className="flex flex-col hover:underline">
                    <span className="font-medium">{toBengaliDigits(purchase.purchase_no)}</span>
                    <span className="text-muted-foreground text-sm">
                        {purchase.supplier.business_name ?? purchase.supplier.name}
                    </span>
                </Link>
            ),
        },
        {
            header: 'তারিখ',
            cell: (purchase) => toBengaliDigits(purchase.purchase_date),
            hideOnMobile: true,
        },
        {
            header: 'ধরন',
            cell: (purchase) => <Badge variant="outline">{purchasePaymentTypeLabels[purchase.payment_type]}</Badge>,
            hideOnMobile: true,
        },
        { header: 'মোট', cell: (purchase) => `৳ ${toBengaliDigits(purchase.total_amount)}` },
        {
            header: 'বাকি',
            cell: (purchase) =>
                Number(purchase.due_amount) > 0 ? (
                    <div className="flex flex-col">
                        <span className="text-destructive font-medium">৳ {toBengaliDigits(purchase.due_amount)}</span>
                        {isOverdue(purchase) && (
                            <span className="text-destructive text-xs">
                                {toBengaliDigits(purchase.payment_due_date!)} তারিখ পার হয়েছে
                            </span>
                        )}
                    </div>
                ) : (
                    '—'
                ),
        },
        {
            header: 'অবস্থা',
            cell: (purchase) => <Badge variant={statusVariant[purchase.status]}>{purchaseStatusLabels[purchase.status]}</Badge>,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="কেনাকাটা" />

            <div className="flex flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">কেনাকাটা</h1>
                    <p className="text-muted-foreground text-sm">কোন চালানে কী এসেছে, আর কত বাকি</p>
                </div>

                <FlashMessages />

                <div className="flex flex-wrap gap-2">
                    <Button
                        variant={status === '' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => router.get(route('purchases.index'), { search }, { preserveState: true, replace: true })}
                    >
                        সব
                    </Button>
                    {statuses.map((option) => (
                        <Button
                            key={option.value}
                            variant={status === String(option.value) ? 'default' : 'outline'}
                            size="sm"
                            onClick={() =>
                                router.get(
                                    route('purchases.index'),
                                    { search, status: option.value },
                                    { preserveState: true, replace: true },
                                )
                            }
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>

                <DataTable
                    rows={purchases}
                    columns={columns}
                    routeName="purchases.index"
                    search={search}
                    searchPlaceholder="চালান নম্বর বা সরবরাহকারী"
                    emptyMessage="এখনো কোনো চালান লেখা হয়নি"
                    rowKey={(purchase) => purchase.id}
                    actions={
                        canRecord && (
                            <Button asChild className="h-11 text-base">
                                <Link href={route('purchases.create')}>
                                    <Plus className="h-4 w-4" />
                                    নতুন চালান
                                </Link>
                            </Button>
                        )
                    }
                />
            </div>
        </AppLayout>
    );
}
