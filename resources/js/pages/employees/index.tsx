import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import { wageTypeLabels } from '@/types/enums';
import type { Employee } from '@/types/models';
import type { Paginated } from '@/types/pagination';

type EmployeeRow = Omit<Employee, 'trade' | 'shop'> & {
    trade: { id: number; name: string } | null;
    shop: { id: number; name: string } | null;
};

interface Props {
    employees: Paginated<EmployeeRow>;
    search: string;
    canManage: boolean;
}

export default function EmployeesIndex({ employees, search, canManage }: Props) {
    const columns: Column<EmployeeRow>[] = [
        {
            header: 'নাম',
            cell: (employee) => (
                <div className="flex flex-col">
                    <span className="font-medium">{employee.name}</span>
                    <span className="text-muted-foreground text-sm">{employee.employee_code}</span>
                </div>
            ),
        },
        { header: 'কাজ', cell: (employee) => employee.trade?.name ?? '—' },
        { header: 'মোবাইল', cell: (employee) => employee.phone ?? '—', hideOnMobile: true },
        {
            header: 'মজুরি',
            cell: (employee) => (
                <div className="flex flex-col">
                    <span>{wageTypeLabels[employee.wage_type]}</span>
                    <span className="text-muted-foreground text-sm">
                        {employee.wage_type === 'daily' && `৳ ${toBengaliDigits(employee.daily_rate)}`}
                        {employee.wage_type === 'monthly' && `৳ ${toBengaliDigits(employee.monthly_salary)}`}
                        {employee.wage_type === 'piece' && 'কাজ অনুযায়ী'}
                    </span>
                </div>
            ),
        },
        { header: 'দোকান', cell: (employee) => employee.shop?.name ?? '—', hideOnMobile: true },
        {
            header: 'অবস্থা',
            cell: (employee) => <Badge variant={employee.is_active ? 'default' : 'secondary'}>{employee.is_active ? 'সক্রিয়' : 'বন্ধ'}</Badge>,
        },
    ];

    return (
        <MasterDataPage
            title="কর্মী"
            subtitle="মিস্ত্রি, হেলপার এবং তাদের মজুরির ধরন"
            resource="employees"
            rows={employees}
            columns={columns}
            search={search}
            searchPlaceholder="নাম, কোড বা মোবাইল"
            emptyMessage="এখনো কোনো কর্মী যোগ করা হয়নি"
            addLabel="নতুন কর্মী"
            canManage={canManage}
            rowKey={(employee) => employee.id}
            deleteConfirm={(employee) => `"${employee.name}" মুছে ফেলতে চান?`}
        />
    );
}
