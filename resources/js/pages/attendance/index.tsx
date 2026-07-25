import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { AttendanceStatus, WageType } from '@/types/enums';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface EmployeeRow {
    id: number;
    name: string;
    employee_code: string;
    trade: string | null;
    wage_type: WageType;
    daily_rate: string;
    status: AttendanceStatus | null;
    in_time: string | null;
    out_time: string | null;
    overtime_hours: string;
    overtime_rate: string;
    note: string | null;
}

interface StatusOption {
    value: AttendanceStatus;
    label: string;
    earnsWage: boolean;
}

interface Props {
    workDate: string;
    shopId: number | null;
    shops: { value: number; label: string }[];
    employees: EmployeeRow[];
    statuses: StatusOption[];
    alreadySaved: boolean;
    canMark: boolean;
}

interface RowState {
    // Inertia's useForm needs an index signature to accept nested rows. The
    // undefined comes from spreading a Partial when one field is patched.
    [key: string]: string | number | undefined;
    employee_id: number;
    status: AttendanceStatus;
    overtime_hours: string;
    overtime_rate: string;
    note: string;
}

interface AttendanceForm {
    [key: string]: string | number | null | RowState[];
    work_date: string;
    shop_id: number | null;
    rows: RowState[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'হাজিরা', href: '/attendance' },
];

export default function AttendanceIndex({ workDate, shopId, shops, employees, statuses, alreadySaved, canMark }: Props) {
    const { data, setData, post, processing, errors } = useForm<AttendanceForm>({
        work_date: workDate,
        shop_id: shopId,
        // Nobody is marked until someone says so, so an unsaved day opens on
        // absent rather than quietly crediting a full crew.
        rows: employees.map((employee) => ({
            employee_id: employee.id,
            status: employee.status ?? 'absent',
            overtime_hours: employee.overtime_hours,
            overtime_rate: employee.overtime_rate,
            note: employee.note ?? '',
        })),
    });

    const [expanded, setExpanded] = useState<number | null>(null);

    const setRow = (employeeId: number, patch: Partial<RowState>) => {
        setData(
            'rows',
            data.rows.map((row) => (row.employee_id === employeeId ? { ...row, ...patch } : row)),
        );
    };

    const markEveryone = (status: AttendanceStatus) => {
        setData(
            'rows',
            data.rows.map((row) => ({ ...row, status })),
        );
    };

    /** Changing the date or shop reloads the sheet for that day. */
    const reload = (params: { date?: string; shop_id?: string }) => {
        router.get(
            route('attendance.index'),
            {
                date: params.date ?? data.work_date,
                ...(params.shop_id !== undefined ? { shop_id: params.shop_id } : data.shop_id ? { shop_id: data.shop_id } : {}),
            },
            { preserveScroll: true },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('attendance.store'));
    };

    const presentCount = data.rows.filter((row) => row.status === 'present').length;
    const halfDayCount = data.rows.filter((row) => row.status === 'half_day').length;

    /** What the shop owes for the day as marked, before it is saved. */
    const dayTotal = data.rows.reduce((sum, row) => {
        const employee = employees.find((e) => e.id === row.employee_id);

        if (!employee || employee.wage_type !== 'daily') return sum;

        const fraction = row.status === 'present' ? 1 : row.status === 'half_day' ? 0.5 : 0;
        const overtime = Number(row.overtime_hours || 0) * Number(row.overtime_rate || 0);

        return sum + Number(employee.daily_rate) * fraction + overtime;
    }, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="হাজিরা" />

            <form onSubmit={submit} className="flex flex-col gap-4 p-4 pb-24">
                <div>
                    <h1 className="text-2xl font-semibold">হাজিরা</h1>
                    <p className="text-muted-foreground text-sm">দিনের কাজ শেষে একবারে সবার হাজিরা দিন</p>
                </div>

                <FlashMessages />

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="work_date">তারিখ</Label>
                        <Input
                            id="work_date"
                            type="date"
                            value={data.work_date}
                            max={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => reload({ date: e.target.value })}
                            className="h-12 text-base"
                        />
                        {errors.work_date && <p className="text-destructive text-sm">{errors.work_date}</p>}
                    </div>

                    {shops.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="shop_id">দোকান</Label>
                            <Select
                                value={data.shop_id ? String(data.shop_id) : '__all__'}
                                onValueChange={(value) => reload({ shop_id: value === '__all__' ? '' : value })}
                            >
                                <SelectTrigger id="shop_id" className="h-12 text-base">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">সব দোকান</SelectItem>
                                    {shops.map((shop) => (
                                        <SelectItem key={shop.value} value={String(shop.value)}>
                                            {shop.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {alreadySaved && (
                    <p className="text-muted-foreground rounded-lg border border-dashed p-3 text-sm">
                        এই দিনের হাজিরা আগেই দেওয়া হয়েছে। বদলে আবার সংরক্ষণ করলে দ্বিতীয়বার টাকা যোগ হবে না।
                    </p>
                )}

                {employees.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        কোনো সক্রিয় কর্মী নেই। আগে কর্মী যোগ করুন।
                    </div>
                ) : (
                    <>
                        {canMark && (
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" className="h-11" onClick={() => markEveryone('present')}>
                                    সবাই উপস্থিত
                                </Button>
                                <Button type="button" variant="outline" className="h-11" onClick={() => markEveryone('absent')}>
                                    সবাই অনুপস্থিত
                                </Button>
                                <Button type="button" variant="outline" className="h-11" onClick={() => markEveryone('holiday')}>
                                    বন্ধের দিন
                                </Button>
                            </div>
                        )}

                        <div className="flex flex-col gap-3">
                            {employees.map((employee) => {
                                const row = data.rows.find((r) => r.employee_id === employee.id)!;
                                const earns = row.status === 'present' || row.status === 'half_day';
                                const isOpen = expanded === employee.id;

                                return (
                                    <div key={employee.id} className="rounded-lg border p-3">
                                        <div className="mb-3 flex items-baseline justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="truncate font-medium">{employee.name}</p>
                                                <p className="text-muted-foreground truncate text-sm">
                                                    {employee.trade ?? employee.employee_code}
                                                    {employee.wage_type === 'daily' && ` · ৳ ${toBengaliDigits(employee.daily_rate)}`}
                                                    {employee.wage_type === 'monthly' && ' · মাসিক বেতন'}
                                                    {employee.wage_type === 'piece' && ' · কাজ চুক্তি'}
                                                </p>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                                            {statuses.map((status) => (
                                                <Button
                                                    key={status.value}
                                                    type="button"
                                                    disabled={!canMark}
                                                    variant={row.status === status.value ? 'default' : 'outline'}
                                                    className={cn('h-11 px-1 text-sm', row.status === status.value && 'font-semibold')}
                                                    onClick={() => setRow(employee.id, { status: status.value })}
                                                >
                                                    {status.label}
                                                </Button>
                                            ))}
                                        </div>

                                        {canMark && earns && (
                                            <button
                                                type="button"
                                                onClick={() => setExpanded(isOpen ? null : employee.id)}
                                                className="text-muted-foreground mt-3 flex items-center gap-1 text-sm"
                                            >
                                                <ChevronDown className={cn('h-4 w-4 transition-transform', isOpen && 'rotate-180')} />
                                                ওভারটাইম ও নোট
                                                {Number(row.overtime_hours) > 0 && (
                                                    <span className="text-foreground font-medium">
                                                        · {toBengaliDigits(row.overtime_hours)} ঘণ্টা
                                                    </span>
                                                )}
                                            </button>
                                        )}

                                        {isOpen && earns && (
                                            <div className="mt-3 grid gap-3 border-t pt-3 sm:grid-cols-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`ot-hours-${employee.id}`}>ওভারটাইম ঘণ্টা</Label>
                                                    <Input
                                                        id={`ot-hours-${employee.id}`}
                                                        type="number"
                                                        inputMode="decimal"
                                                        step="0.5"
                                                        min="0"
                                                        value={row.overtime_hours}
                                                        onChange={(e) => setRow(employee.id, { overtime_hours: e.target.value })}
                                                        className="h-12 text-base"
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`ot-rate-${employee.id}`}>ঘণ্টা প্রতি</Label>
                                                    <Input
                                                        id={`ot-rate-${employee.id}`}
                                                        type="number"
                                                        inputMode="numeric"
                                                        min="0"
                                                        value={row.overtime_rate}
                                                        onChange={(e) => setRow(employee.id, { overtime_rate: e.target.value })}
                                                        className="h-12 text-base"
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`note-${employee.id}`}>নোট</Label>
                                                    <Input
                                                        id={`note-${employee.id}`}
                                                        value={row.note}
                                                        onChange={(e) => setRow(employee.id, { note: e.target.value })}
                                                        className="h-12 text-base"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}

                {canMark && employees.length > 0 && (
                    <div className="bg-background/95 fixed inset-x-0 bottom-0 border-t p-4 backdrop-blur">
                        <div className="mx-auto flex max-w-5xl items-center gap-4">
                            <div className="min-w-0 flex-1 text-sm">
                                <p className="font-medium">
                                    উপস্থিত {toBengaliDigits(presentCount)}
                                    {halfDayCount > 0 && ` · অর্ধদিবস ${toBengaliDigits(halfDayCount)}`}
                                </p>
                                <p className="text-muted-foreground">দিনের মজুরি ৳ {toBengaliDigits(dayTotal.toFixed(2))}</p>
                            </div>
                            <Button type="submit" className="h-12 px-8 text-base" disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                সংরক্ষণ করুন
                            </Button>
                        </div>
                    </div>
                )}
            </form>
        </AppLayout>
    );
}
