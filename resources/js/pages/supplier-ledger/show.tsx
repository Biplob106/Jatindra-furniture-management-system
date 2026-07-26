import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { supplierLedgerEntryTypeLabels, supplierTypeLabels, type LedgerDirection, type SupplierLedgerEntryType, type SupplierType } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Entry {
    id: number;
    entry_date: string;
    type: SupplierLedgerEntryType;
    direction: LedgerDirection;
    amount: string;
    note: string | null;
}

interface OpenChallan {
    id: number;
    purchase_no: string;
    purchase_date: string;
    payment_due_date: string | null;
    total_amount: string;
    due_amount: string;
    age_days: number;
    overdue: boolean;
}

interface AccountOption extends Option {
    balance: string;
}

interface PaymentForm {
    [key: string]: string;
    amount: string;
    payment_date: string;
    account_id: string;
    payment_method: string;
    reference_no: string;
    note: string;
}

interface Props {
    supplier: {
        id: number;
        name: string;
        business_name: string | null;
        phone: string | null;
        supplier_type: SupplierType;
        default_credit_days: number;
        credit_limit: string;
    };
    entries: Paginated<Entry>;
    balance: string;
    openChallans: OpenChallan[];
    accounts: AccountOption[];
    paymentMethods: Option[];
    today: string;
    canPay: boolean;
}

export default function SupplierLedgerShow({
    supplier,
    entries,
    balance,
    openChallans,
    accounts,
    paymentMethods,
    today,
    canPay,
}: Props) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<PaymentForm>({
        amount: '',
        payment_date: today,
        account_id: accounts.length === 1 ? String(accounts[0].value) : '',
        payment_method: 'cash',
        reference_no: '',
        note: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'সরবরাহকারীর হিসাব', href: '/supplier-ledger' },
        { title: supplier.name, href: '#' },
    ];

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('supplier-ledger.store', supplier.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset('amount', 'reference_no', 'note');
                setOpen(false);
            },
        });
    };

    const owed = Number(balance);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={supplier.name} />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">{supplier.name}</h1>
                    <p className="text-muted-foreground text-sm">
                        {supplier.business_name ?? supplierTypeLabels[supplier.supplier_type]}
                        {supplier.phone && ` — ${toBengaliDigits(supplier.phone)}`}
                    </p>
                </div>

                <FlashMessages />

                {/* Positive means we owe them. Negative is money paid ahead. */}
                <div className="rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">{owed >= 0 ? 'এখন বাকি' : 'আগাম দেওয়া আছে'}</p>
                    <p className="text-2xl font-semibold">
                        <Money amount={owed < 0 ? balance.replace('-', '') : balance} />
                    </p>
                </div>

                {canPay && !open && (
                    <Button className="h-12 text-base" onClick={() => setOpen(true)}>
                        টাকা পরিশোধ করুন
                    </Button>
                )}

                {canPay && open && (
                    <form onSubmit={submit} className="flex flex-col gap-4 rounded-lg border p-4">
                        <p className="font-medium">টাকা পরিশোধ</p>
                        <p className="text-muted-foreground text-sm">
                            পুরোনো চালান আগে শোধ হবে। বাড়তি টাকা থাকলে তা জমা হিসেবে থাকবে।
                        </p>

                        <TextField
                            id="amount"
                            label="কত টাকা"
                            type="number"
                            numeric
                            value={data.amount}
                            onChange={(v) => setData('amount', v)}
                            error={errors.amount}
                            required
                            autoFocus
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <SelectField
                                id="account_id"
                                label="কোন হিসাব থেকে"
                                value={data.account_id}
                                onChange={(v) => setData('account_id', v)}
                                options={accounts.map((account) => ({
                                    value: String(account.value),
                                    label: `${account.label} — ৳ ${toBengaliDigits(account.balance)}`,
                                }))}
                                error={errors.account_id}
                                required
                            />

                            <SelectField
                                id="payment_method"
                                label="কীভাবে"
                                value={data.payment_method}
                                onChange={(v) => setData('payment_method', v)}
                                options={paymentMethods}
                                error={errors.payment_method}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id="payment_date"
                                label="তারিখ"
                                type="date"
                                value={data.payment_date}
                                onChange={(v) => setData('payment_date', v)}
                                error={errors.payment_date}
                                required
                            />

                            <TextField
                                id="reference_no"
                                label="রেফারেন্স"
                                value={data.reference_no}
                                onChange={(v) => setData('reference_no', v)}
                                error={errors.reference_no}
                                hint="চেক নম্বর বা লেনদেন আইডি"
                            />
                        </div>

                        <TextField id="note" label="নোট" value={data.note} onChange={(v) => setData('note', v)} error={errors.note} />

                        <div className="flex gap-2">
                            <Button type="submit" disabled={processing} className="h-11 flex-1 text-base">
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                সংরক্ষণ
                            </Button>
                            <Button type="button" variant="outline" className="h-11" onClick={() => setOpen(false)}>
                                বাতিল
                            </Button>
                        </div>
                    </form>
                )}

                {openChallans.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <p className="font-medium">বাকি থাকা চালান</p>
                        {openChallans.map((challan) => (
                            <div key={challan.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{toBengaliDigits(challan.purchase_no)}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {toBengaliDigits(challan.purchase_date)} — {toBengaliDigits(challan.age_days)} দিন
                                    </p>
                                </div>
                                <div className="text-right">
                                    <Money amount={challan.due_amount} className="font-semibold" />
                                    {challan.overdue && (
                                        <p>
                                            <Badge variant="destructive">মেয়াদ পার</Badge>
                                        </p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex flex-col gap-2">
                    <p className="font-medium">হিসাবের খাতা</p>

                    {entries.data.length === 0 ? (
                        <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                            এখনো কোনো হিসাব লেখা হয়নি
                        </div>
                    ) : (
                        entries.data.map((entry) => (
                            <div key={entry.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">{supplierLedgerEntryTypeLabels[entry.type]}</p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {toBengaliDigits(entry.entry_date)}
                                        {entry.note && ` — ${entry.note}`}
                                    </p>
                                </div>
                                <span
                                    className={
                                        entry.direction === 'credit'
                                            ? 'text-destructive font-semibold'
                                            : 'font-semibold text-emerald-600'
                                    }
                                >
                                    {entry.direction === 'credit' ? '+' : '−'} <Money amount={entry.amount} />
                                </span>
                            </div>
                        ))
                    )}
                </div>

                {entries.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {entries.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={link.url === null}
                                asChild={link.url !== null}
                            >
                                {link.url !== null ? (
                                    <Link href={link.url} preserveScroll>
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Link>
                                ) : (
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
