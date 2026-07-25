import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import type { Trade } from '@/types/models';
import type { Paginated } from '@/types/pagination';

interface TradeRow extends Trade {
    employees_count: number;
}

interface Props {
    trades: Paginated<TradeRow>;
    search: string;
    canManage: boolean;
}

export default function TradesIndex({ trades, search, canManage }: Props) {
    const columns: Column<TradeRow>[] = [
        { header: 'কাজের ধরন', cell: (trade) => <span className="font-medium">{trade.name}</span> },
        { header: 'দৈনিক হার', cell: (trade) => `৳ ${toBengaliDigits(trade.default_daily_rate)}` },
        { header: 'কর্মী', cell: (trade) => toBengaliDigits(trade.employees_count), hideOnMobile: true },
        {
            header: 'অবস্থা',
            cell: (trade) => <Badge variant={trade.is_active ? 'default' : 'secondary'}>{trade.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="কাজের ধরন"
            subtitle="বার্নিশ, নকশা, প্লেন কাঠ, সিএনসি, হেলপার"
            resource="trades"
            rows={trades}
            columns={columns}
            search={search}
            searchPlaceholder="কাজের ধরন"
            emptyMessage="এখনো কোনো কাজের ধরন যোগ করা হয়নি"
            addLabel="নতুন ধরন"
            canManage={canManage}
            rowKey={(trade) => trade.id}
            deleteConfirm={(trade) => `"${trade.name}" মুছে ফেলতে চান?`}
        />
    );
}
