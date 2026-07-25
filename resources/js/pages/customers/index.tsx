import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import { customerTypeLabels } from '@/types/enums';
import type { Customer } from '@/types/models';
import type { Paginated } from '@/types/pagination';

interface Props {
    customers: Paginated<Customer>;
    search: string;
    canManage: boolean;
}

export default function CustomersIndex({ customers, search, canManage }: Props) {
    const columns: Column<Customer>[] = [
        {
            header: 'নাম',
            cell: (customer) => (
                <div className="flex flex-col">
                    <span className="font-medium">{customer.name}</span>
                    <span className="text-muted-foreground text-sm sm:hidden">{customer.phone}</span>
                </div>
            ),
        },
        { header: 'মোবাইল', cell: (customer) => customer.phone, hideOnMobile: true },
        { header: 'এলাকা', cell: (customer) => customer.area ?? '—', hideOnMobile: true },
        { header: 'ধরন', cell: (customer) => <Badge variant="outline">{customerTypeLabels[customer.customer_type]}</Badge> },
        {
            header: 'বকেয়া',
            cell: (customer) =>
                Number(customer.opening_due) > 0 ? (
                    <span className="text-destructive font-medium">৳ {toBengaliDigits(customer.opening_due)}</span>
                ) : (
                    '—'
                ),
        },
    ];

    return (
        <MasterDataPage
            title="কাস্টমার"
            subtitle="মোবাইল নম্বর দিয়ে খুঁজুন"
            resource="customers"
            rows={customers}
            columns={columns}
            search={search}
            searchPlaceholder="মোবাইল নম্বর, নাম বা এলাকা"
            emptyMessage="এখনো কোনো কাস্টমার যোগ করা হয়নি"
            addLabel="নতুন কাস্টমার"
            canManage={canManage}
            rowKey={(customer) => customer.id}
            deleteConfirm={(customer) => `"${customer.name}" মুছে ফেলতে চান?`}
        />
    );
}
