import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/number-format';
import { type BreadcrumbItem } from '@/types';
import { ledgerEntryTypeLabels, paymentMethodLabels, type LedgerDirection, type LedgerEntryType, type PaymentMethod, type WageType } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Entry {
    id: number;
    entry_date: string;
    type: LedgerEntryType;
    direction: LedgerDirection;
    amount: string;
    payment_method: PaymentMethod | null;
    category: string | null;
    note: string | null;
}

interface PaymentType extends Option {
    movesCash: boolean;
}

interface AccountOption extends Option {
    balance: string;
}

interface Props {
    employee: {
        id: number;
        name: string;
        employee_code: string;
        phone: string | null;
        wage_type: WageType;
        daily_rate: string;
        monthly_salary: string;
    };
    entries: Paginated<Entry>;
    balance: string;
    totals: { earned: string; taken: string };
    paymentTypes: PaymentType[];
    paymentMethods: Option[];
    accounts: AccountOption[];
    deductionCategories: Option[];
    canPay: boolean;
    month: string;
    monthSummary: {
        present: number;
        half_day: number;
        night_only: number;
        full_day_night: number;
        half_day_night: number;
        absent: number;
        leave: number;
        holiday: number;
        nights: number;
        worked_days: string;
        earned: string;
        night_pay: string;
        advance: string;
        tiffin: string;
        payout: string;
        deductions: string;
    };
}

interface PaymentForm {
    [key: string]: string;
    type: string;
    amount: string;
    entry_date: string;
    account_id: string;
    payment_method: string;
    deduction_category_id: string;
    note: string;
}

export default function EmployeeLedgerShow({
    employee,
    entries,
    balance,
    totals,
    paymentTypes,
    paymentMethods,
    accounts,
    deductionCategories,
    canPay,
    month,
    monthSummary,
}: Props) {
    const [showForm, setShowForm] = useState(false);

    const changeMonth = (value: string) => {
        router.get(route('employee-ledger.show', employee.id), value ? { month: value } : {}, {
            preserveScroll: true,
            preserveState: false,
            replace: true,
        });
    };

    const { data, setData, post, processing, errors, reset } = useForm<PaymentForm>({
        type: 'payout',
        amount: '',
        entry_date: new Date().toISOString().slice(0, 10),
        account_id: accounts.length === 1 ? String(accounts[0].value) : '',
        payment_method: 'cash',
        deduction_category_id: '',
        note: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'কর্মীর পাওনা', href: '/employee-ledger' },
        { title: employee.name, href: `/employee-ledger/${employee.id}` },
    ];

    const selectedType = paymentTypes.find((type) => type.value === data.type);
    const movesCash = selectedType?.movesCash ?? false;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('employee-ledger.store', employee.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset('amount', 'note');
                setShowForm(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={employee.name} />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{employee.name}</h1>
                    <p className="text-muted-foreground text-sm">
                        {employee.employee_code}
                        {employee.phone && ` · ${toBengaliDigits(employee.phone)}`}
                    </p>
                </div>

                <FlashMessages />

                <div className="rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">
                        {Number(balance) < 0 ? 'কর্মীর কাছে পাওনা' : 'কর্মীকে দিতে হবে'}
                    </p>
                    <p className="text-3xl font-semibold">
                        <Money amount={balance} signed />
                    </p>
                    <div className="text-muted-foreground mt-3 flex gap-6 border-t pt-3 text-sm">
                        <span>
                            মোট জমা <Money amount={totals.earned} className="text-foreground font-medium" />
                        </span>
                        <span>
                            মোট নেওয়া <Money amount={totals.taken} className="text-foreground font-medium" />
                        </span>
                    </div>
                </div>

                <div className="rounded-lg border">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                        <h2 className="font-medium">মাসিক হিসাব</h2>
                        <input
                            type="month"
                            value={month}
                            max={new Date().toISOString().slice(0, 7)}
                            onChange={(e) => changeMonth(e.target.value)}
                            className="border-input bg-background h-11 rounded-md border px-3 text-base"
                            aria-label="মাস"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-px bg-border sm:grid-cols-4">
                        {[
                            { label: 'কর্মদিবস', value: toBengaliDigits(monthSummary.worked_days) },
                            { label: 'উপস্থিত', value: toBengaliDigits(monthSummary.present) },
                            { label: 'অর্ধদিবস', value: toBengaliDigits(monthSummary.half_day) },
                            { label: 'রাত', value: toBengaliDigits(monthSummary.nights) },
                            { label: 'পূর্ণ দিন + রাত', value: toBengaliDigits(monthSummary.full_day_night) },
                            { label: 'শুধু রাত', value: toBengaliDigits(monthSummary.night_only) },
                            { label: 'অনুপস্থিত', value: toBengaliDigits(monthSummary.absent) },
                            { label: 'ছুটি', value: toBengaliDigits(monthSummary.leave + monthSummary.holiday) },
                        ].map((stat) => (
                            <div key={stat.label} className="bg-background p-3">
                                <p className="text-muted-foreground text-xs">{stat.label}</p>
                                <p className="text-lg font-semibold tabular-nums">{stat.value}</p>
                            </div>
                        ))}
                    </div>

                    <div className="text-muted-foreground flex flex-wrap gap-x-6 gap-y-2 border-t p-4 text-sm">
                        <span>
                            এই মাসে জমা <Money amount={monthSummary.earned} className="text-foreground font-medium" />
                        </span>
                        <span>
                            এর মধ্যে রাত্রি ভাতা <Money amount={monthSummary.night_pay} className="text-foreground font-medium" />
                        </span>
                        <span>
                            অগ্রিম <Money amount={monthSummary.advance} className="text-foreground font-medium" />
                        </span>
                        <span>
                            টিফিন <Money amount={monthSummary.tiffin} className="text-foreground font-medium" />
                        </span>
                        <span>
                            পরিশোধ <Money amount={monthSummary.payout} className="text-foreground font-medium" />
                        </span>
                        <span>
                            কর্তন <Money amount={monthSummary.deductions} className="text-foreground font-medium" />
                        </span>
                    </div>
                </div>

                {canPay && !showForm && (
                    <Button onClick={() => setShowForm(true)} className="h-12 text-base">
                        <Plus className="h-4 w-4" />
                        টাকা দিন বা হিসাব যোগ করুন
                    </Button>
                )}

                {canPay && showForm && (
                    <form onSubmit={submit} className="flex flex-col gap-4 rounded-lg border p-4">
                        <SelectField
                            id="type"
                            label="ধরন"
                            value={data.type}
                            onChange={(value) => setData('type', value)}
                            options={paymentTypes}
                            error={errors.type}
                            required
                        />

                        <TextField
                            id="amount"
                            label="টাকার পরিমাণ"
                            type="number"
                            numeric
                            value={data.amount}
                            onChange={(value) => setData('amount', value)}
                            error={errors.amount}
                            required
                            autoFocus
                        />

                        {data.type === 'deduction' && (
                            <SelectField
                                id="deduction_category_id"
                                label="কর্তনের খাত"
                                value={data.deduction_category_id}
                                onChange={(value) => setData('deduction_category_id', value)}
                                options={deductionCategories}
                                error={errors.deduction_category_id}
                                required
                            />
                        )}

                        {movesCash ? (
                            <>
                                <SelectField
                                    id="account_id"
                                    label="কোন হিসাব থেকে"
                                    value={data.account_id}
                                    onChange={(value) => setData('account_id', value)}
                                    options={accounts.map((account) => ({
                                        value: account.value,
                                        label: `${account.label} — ৳ ${formatMoney(account.balance)}`,
                                    }))}
                                    error={errors.account_id}
                                    required
                                />

                                <SelectField
                                    id="payment_method"
                                    label="কীভাবে দেওয়া হলো"
                                    value={data.payment_method}
                                    onChange={(value) => setData('payment_method', value)}
                                    options={paymentMethods}
                                    error={errors.payment_method}
                                />
                            </>
                        ) : (
                            <p className="text-muted-foreground bg-muted/50 rounded-lg border p-3 text-sm">
                                এতে কোনো টাকা হাতে দেওয়া হচ্ছে না, শুধু কর্মীর হিসাব বদলাবে।
                            </p>
                        )}

                        <TextField
                            id="entry_date"
                            label="তারিখ"
                            type="date"
                            value={data.entry_date}
                            onChange={(value) => setData('entry_date', value)}
                            error={errors.entry_date}
                            required
                        />

                        <TextField
                            id="note"
                            label="নোট"
                            value={data.note}
                            onChange={(value) => setData('note', value)}
                            error={errors.note}
                        />

                        <div className="flex gap-3">
                            <Button type="submit" className="h-12 flex-1 text-base" disabled={processing}>
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                সংরক্ষণ করুন
                            </Button>
                            <Button type="button" variant="outline" className="h-12 text-base" onClick={() => setShowForm(false)}>
                                বাতিল
                            </Button>
                        </div>
                    </form>
                )}

                <div className="flex flex-col gap-2">
                    <h2 className="font-medium">হিসাবের খাতা</h2>

                    {entries.data.length === 0 ? (
                        <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                            এখনো কোনো হিসাব নেই
                        </div>
                    ) : (
                        entries.data.map((entry) => (
                            <div key={entry.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">{ledgerEntryTypeLabels[entry.type]}</span>
                                        {entry.category && <Badge variant="outline">{entry.category}</Badge>}
                                        {entry.payment_method && (
                                            <Badge variant="outline">{paymentMethodLabels[entry.payment_method]}</Badge>
                                        )}
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        {toBengaliDigits(entry.entry_date)}
                                        {entry.note && ` · ${entry.note}`}
                                    </p>
                                </div>
                                <span
                                    className={
                                        entry.direction === 'credit'
                                            ? 'font-semibold text-green-700 tabular-nums dark:text-green-400'
                                            : 'text-destructive font-semibold tabular-nums'
                                    }
                                >
                                    {entry.direction === 'credit' ? '+' : '−'} ৳ {formatMoney(entry.amount)}
                                </span>
                            </div>
                        ))
                    )}

                    {entries.last_page > 1 && (
                        <div className="flex justify-between gap-2 pt-2">
                            <Button variant="outline" size="sm" asChild disabled={entries.current_page === 1}>
                                <Link href={entries.links.find((l) => l.label === String(entries.current_page - 1))?.url ?? '#'} preserveScroll>
                                    আগের
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild disabled={entries.current_page === entries.last_page}>
                                <Link href={entries.links.find((l) => l.label === String(entries.current_page + 1))?.url ?? '#'} preserveScroll>
                                    পরের
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
