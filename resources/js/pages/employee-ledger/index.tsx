import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Money } from '@/components/money';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { WageType } from '@/types/enums';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface WorkerRow {
    id: number;
    name: string;
    employee_code: string;
    trade: string | null;
    wage_type: WageType;
    balance: string;
}

interface Props {
    employees: WorkerRow[];
    search: string;
    totalOwed: string;
    canPay: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'কর্মীর হিসাব', href: '/employee-ledger' },
];

export default function EmployeeLedgerIndex({ employees, search, totalOwed }: Props) {
    const [term, setTerm] = useState(search);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => {
            router.get(route('employee-ledger.index'), term ? { search: term } : {}, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(timer);
    }, [term]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="কর্মীর হিসাব" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">কর্মীর হিসাব</h1>
                    <p className="text-muted-foreground text-sm">কার কত পাওনা আছে</p>
                </div>

                <FlashMessages />

                <div className="rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">সব কর্মীর মোট পাওনা</p>
                    <p className="text-2xl font-semibold">
                        <Money amount={totalOwed} />
                    </p>
                </div>

                <div className="relative sm:max-w-xs">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="নাম, কোড বা মোবাইল"
                        className="h-11 pr-9 text-base"
                    />
                </div>

                {employees.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        {search ? `"${search}" এর জন্য কিছু পাওয়া যায়নি` : 'কোনো সক্রিয় কর্মী নেই'}
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {employees.map((worker) => (
                            <Link
                                key={worker.id}
                                href={route('employee-ledger.show', worker.id)}
                                className="hover:bg-muted/50 flex items-center gap-3 rounded-lg border p-4 transition-colors"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{worker.name}</p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {worker.trade ?? worker.employee_code}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Money amount={worker.balance} signed className="font-semibold" />
                                </div>
                                <ChevronLeft className="text-muted-foreground h-4 w-4 shrink-0" />
                            </Link>
                        ))}
                    </div>
                )}

                <p className="text-muted-foreground text-sm">
                    মোট {toBengaliDigits(employees.length)} জন কর্মী
                </p>
            </div>
        </AppLayout>
    );
}
