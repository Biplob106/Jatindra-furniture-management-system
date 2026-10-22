import { Column } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import type { DeductionCategory } from '@/types/models';
import type { Paginated } from '@/types/pagination';

interface Props {
    categories: Paginated<DeductionCategory>;
    search: string;
    canManage: boolean;
}

export default function DeductionCategoriesIndex({ categories, search, canManage }: Props) {
    const columns: Column<DeductionCategory>[] = [
        { header: 'কর্তনের খাত', cell: (category) => <span className="font-medium">{category.name}</span> },
        {
            header: 'অবস্থা',
            cell: (category) => <Badge variant={category.is_active ? 'default' : 'secondary'}>{category.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="কর্তনের খাত"
            subtitle="ভাঙা মালের ক্ষতি, ইউনিফর্ম, ঋণের কিস্তি"
            resource="deduction-categories"
            rows={categories}
            columns={columns}
            search={search}
            searchPlaceholder="খাতের নাম"
            emptyMessage="এখনো কোনো কর্তনের খাত যোগ করা হয়নি"
            addLabel="নতুন খাত"
            canManage={canManage}
            rowKey={(category) => category.id}
            deleteConfirm={(category) => `"${category.name}" মুছে ফেলতে চান?`}
        />
    );
}
