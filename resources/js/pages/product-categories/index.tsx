import { Column } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import type { ProductCategory } from '@/types/models';
import type { Paginated } from '@/types/pagination';

/** The list only eager-loads id and name on the parent, not the whole record. */
type CategoryRow = Omit<ProductCategory, 'parent'> & {
    parent: { id: number; name: string } | null;
};

interface Props {
    categories: Paginated<CategoryRow>;
    search: string;
    canManage: boolean;
}

export default function ProductCategoriesIndex({ categories, search, canManage }: Props) {
    const columns: Column<CategoryRow>[] = [
        {
            header: 'ক্যাটাগরি',
            cell: (category) => (
                <div className="flex flex-col">
                    <span className="font-medium">{category.name}</span>
                    {category.parent && <span className="text-muted-foreground text-sm sm:hidden">{category.parent.name} এর ভেতরে</span>}
                </div>
            ),
        },
        { header: 'প্যারেন্ট', cell: (category) => category.parent?.name ?? '—', hideOnMobile: true },
        {
            header: 'অবস্থা',
            cell: (category) => <Badge variant={category.is_active ? 'default' : 'secondary'}>{category.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="পণ্যের ক্যাটাগরি"
            subtitle="খাট, আলমারি, সোফা, ড্রেসিং টেবিল"
            resource="product-categories"
            rows={categories}
            columns={columns}
            search={search}
            searchPlaceholder="ক্যাটাগরির নাম"
            emptyMessage="এখনো কোনো ক্যাটাগরি যোগ করা হয়নি"
            addLabel="নতুন ক্যাটাগরি"
            canManage={canManage}
            rowKey={(category) => category.id}
            deleteConfirm={(category) => `"${category.name}" মুছে ফেলতে চান?`}
        />
    );
}
