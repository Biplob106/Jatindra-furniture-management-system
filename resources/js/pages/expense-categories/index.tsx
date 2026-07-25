import { Column } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import type { ExpenseCategory } from '@/types/models';
import type { Paginated } from '@/types/pagination';

interface Props {
    categories: Paginated<ExpenseCategory>;
    search: string;
    canManage: boolean;
}

export default function ExpenseCategoriesIndex({ categories, search, canManage }: Props) {
    const columns: Column<ExpenseCategory>[] = [
        { header: 'খরচের খাত', cell: (category) => <span className="font-medium">{category.name}</span> },
        {
            header: 'প্রতি মাসে',
            cell: (category) => (category.is_recurring ? <Badge variant="outline">প্রতি মাসে</Badge> : '—'),
        },
        {
            header: 'অবস্থা',
            cell: (category) => <Badge variant={category.is_active ? 'default' : 'secondary'}>{category.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="খরচের খাত"
            subtitle="দোকান ভাড়া, কারেন্ট বিল, চা-নাস্তা, ট্রান্সপোর্ট"
            resource="expense-categories"
            rows={categories}
            columns={columns}
            search={search}
            searchPlaceholder="খাতের নাম"
            emptyMessage="এখনো কোনো খরচের খাত যোগ করা হয়নি"
            addLabel="নতুন খাত"
            canManage={canManage}
            rowKey={(category) => category.id}
            deleteConfirm={(category) => `"${category.name}" মুছে ফেলতে চান?`}
        />
    );
}
