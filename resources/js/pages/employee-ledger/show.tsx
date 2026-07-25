import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { ledgerEntryTypeLabels, paymentMethodLabels, type LedgerDirection, type LedgerEntryType, type PaymentMethod, type WageType } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Entry {
    id: number;
    entry_date: string;
    type: LedgerEntryType;
    direction: LedgerDirection;
    amount: string;
    payment_method: PaymentMethod | null;
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
    canPay: boolean;
}

interface PaymentForm {
    [key: string]: string;
    type: string;
    amount: string;
    entry_date: string;
    account_id: string;
    payment_method: string;
    note: string;
}

export default function EmployeeLedgerShow({ employee, entries, balance, totals, paymentTypes, paymentMethods, accounts, canPay }: Props) {
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<PaymentForm>({
        type: 'payout',
        amount: '',
        entry_date: new Date().toISOString().slice(0, 10),
        account_id: accounts.length === 1 ? String(accounts[0].value) : '',
        payment_method: 'cash',
        note: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'কর্মীর হিসাব', href: '/employee-ledger' },
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

                        {movesCash ? (
                            <>
                                <SelectField
                                    id="account_id"
                                    label="কোন হিসাব থেকে"
                                    value={data.account_id}
                                    onChange={(value) => setData('account_id', value)}
                                    options={accounts.map((account) => ({
                                        value: account.value,
                                        label: `${account.label} — ৳ ${toBengaliDigits(account.balance)}`,
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
                                    {entry.direction === 'credit' ? '+' : '−'} ৳ {toBengaliDigits(entry.amount)}
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
