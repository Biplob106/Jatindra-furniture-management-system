import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import { supplierTypeLabels } from '@/types/enums';
import type { Supplier } from '@/types/models';
import type { Paginated } from '@/types/pagination';

/** The list carries the ledger balance, which is not a column on the row. */
type SupplierRow = Supplier & { balance: string };

interface Props {
    suppliers: Paginated<SupplierRow>;
    search: string;
    canManage: boolean;
}

export default function SuppliersIndex({ suppliers, search, canManage }: Props) {
    const columns: Column<SupplierRow>[] = [
        {
            header: 'নাম',
            cell: (supplier) => (
                <div className="flex flex-col">
                    <span className="font-medium">{supplier.name}</span>
                    <span className="text-muted-foreground text-sm">{supplier.business_name ?? supplier.phone ?? '—'}</span>
                </div>
            ),
        },
        { header: 'মোবাইল', cell: (supplier) => supplier.phone ?? '—', hideOnMobile: true },
        {
            header: 'ধরন',
            cell: (supplier) => <Badge variant="outline">{supplierTypeLabels[supplier.supplier_type]}</Badge>,
            hideOnMobile: true,
        },
        {
            header: 'বাকির মেয়াদ',
            cell: (supplier) => (supplier.default_credit_days > 0 ? `${toBengaliDigits(supplier.default_credit_days)} দিন` : '—'),
            hideOnMobile: true,
        },
        {
            /**
             * Positive means we owe them. A negative balance is money paid
             * ahead, so it is shown as such rather than as a debt.
             */
            header: 'বাকি',
            cell: (supplier) => {
                const balance = Number(supplier.balance);

                if (balance > 0) {
                    return <span className="text-destructive font-medium">৳ {toBengaliDigits(supplier.balance)}</span>;
                }

                if (balance < 0) {
                    return <span className="font-medium text-emerald-600">৳ {toBengaliDigits(supplier.balance.replace('-', ''))} জমা</span>;
                }

                return '—';
            },
        },
    ];

    return (
        <MasterDataPage
            title="সরবরাহকারী"
            subtitle="কার কাছ থেকে মাল আসে, আর কত বাকি"
            resource="suppliers"
            rows={suppliers}
            columns={columns}
            search={search}
            searchPlaceholder="নাম, দোকান বা মোবাইল নম্বর"
            emptyMessage="এখনো কোনো সরবরাহকারী যোগ করা হয়নি"
            addLabel="নতুন সরবরাহকারী"
            canManage={canManage}
            rowKey={(supplier) => supplier.id}
            deleteConfirm={(supplier) => `"${supplier.name}" মুছে ফেলতে চান?`}
        />
    );
}
