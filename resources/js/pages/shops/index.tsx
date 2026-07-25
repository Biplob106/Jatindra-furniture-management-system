import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import type { Shop } from '@/types/models';
import type { Paginated } from '@/types/pagination';

interface Props {
    shops: Paginated<Shop>;
    search: string;
    canManage: boolean;
}

export default function ShopsIndex({ shops, search, canManage }: Props) {
    const columns: Column<Shop>[] = [
        {
            header: 'নাম',
            cell: (shop) => (
                <div className="flex flex-col">
                    <span className="font-medium">{shop.name}</span>
                    {shop.phone && <span className="text-muted-foreground text-sm sm:hidden">{shop.phone}</span>}
                </div>
            ),
        },
        { header: 'মোবাইল', cell: (shop) => shop.phone ?? '—', hideOnMobile: true },
        { header: 'মাসিক ভাড়া', cell: (shop) => `৳ ${toBengaliDigits(shop.monthly_rent)}` },
        { header: 'ভাড়ার তারিখ', cell: (shop) => (shop.rent_due_day ? toBengaliDigits(shop.rent_due_day) : '—'), hideOnMobile: true },
        {
            header: 'অবস্থা',
            cell: (shop) => <Badge variant={shop.is_active ? 'default' : 'secondary'}>{shop.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="দোকান"
            subtitle="দোকানের তথ্য, ভাড়া এবং বাড়িওয়ালার যোগাযোগ"
            resource="shops"
            rows={shops}
            columns={columns}
            search={search}
            searchPlaceholder="দোকানের নাম বা মোবাইল"
            emptyMessage="এখনো কোনো দোকান যোগ করা হয়নি"
            addLabel="নতুন দোকান"
            canManage={canManage}
            rowKey={(shop) => shop.id}
            deleteConfirm={(shop) => `"${shop.name}" মুছে ফেলতে চান?`}
        />
    );
}
