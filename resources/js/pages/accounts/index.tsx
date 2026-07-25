import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import { accountTypeLabels } from '@/types/enums';
import type { Account } from '@/types/models';
import type { Paginated } from '@/types/pagination';

interface AccountRow extends Account {
    shop: { id: number; name: string } | null;
}

interface Props {
    accounts: Paginated<AccountRow>;
    search: string;
    canManage: boolean;
}

export default function AccountsIndex({ accounts, search, canManage }: Props) {
    const columns: Column<AccountRow>[] = [
        {
            header: 'হিসাবের নাম',
            cell: (account) => (
                <div className="flex flex-col">
                    <span className="font-medium">{account.name}</span>
                    <span className="text-muted-foreground text-sm sm:hidden">{accountTypeLabels[account.type]}</span>
                </div>
            ),
        },
        { header: 'ধরন', cell: (account) => accountTypeLabels[account.type], hideOnMobile: true },
        { header: 'দোকান', cell: (account) => account.shop?.name ?? 'সব দোকান', hideOnMobile: true },
        {
            header: 'বর্তমান জমা',
            cell: (account) => <span className="font-medium">৳ {toBengaliDigits(account.current_balance)}</span>,
        },
        {
            header: 'অবস্থা',
            cell: (account) => <Badge variant={account.is_active ? 'default' : 'secondary'}>{account.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="হিসাব"
            subtitle="ক্যাশ বাক্স, বিকাশ, নগদ, ব্যাংক"
            resource="accounts"
            rows={accounts}
            columns={columns}
            search={search}
            searchPlaceholder="হিসাবের নাম"
            emptyMessage="এখনো কোনো হিসাব যোগ করা হয়নি"
            addLabel="নতুন হিসাব"
            canManage={canManage}
            rowKey={(account) => account.id}
            deleteConfirm={(account) => `"${account.name}" মুছে ফেলতে চান?`}
        />
    );
}
