import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface AccountOption extends Option {
    balance: string;
}

interface Props {
    categories: Option[];
    accounts: AccountOption[];
    shops: Option[];
    paymentMethods: Option[];
    today: string;
}

interface ExpenseForm {
    [key: string]: string;
    category_id: string;
    account_id: string;
    expense_date: string;
    amount: string;
    paid_to: string;
    payment_method: string;
    shop_id: string;
    note: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'খরচ', href: '/expenses' },
    { title: 'নতুন', href: '#' },
];

export default function CreateExpense({ categories, accounts, shops, paymentMethods, today }: Props) {
    const { data, setData, post, processing, errors } = useForm<ExpenseForm>({
        category_id: '',
        account_id: accounts.length === 1 ? String(accounts[0].value) : '',
        expense_date: today,
        amount: '',
        paid_to: '',
        payment_method: 'cash',
        shop_id: shops.length === 1 ? String(shops[0].value) : '',
        note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('expenses.store'));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="নতুন খরচ" />

            <form onSubmit={submit} className="flex max-w-2xl flex-col gap-5 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">নতুন খরচ</h1>
                    <p className="text-muted-foreground text-sm">টাকা হিসাব থেকে কেটে নেওয়া হবে</p>
                </div>

                <FlashMessages />

                <SelectField
                    id="category_id"
                    label="খরচের খাত"
                    value={data.category_id}
                    onChange={(value) => setData('category_id', value)}
                    options={categories}
                    error={errors.category_id}
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

                <TextField
                    id="expense_date"
                    label="তারিখ"
                    type="date"
                    value={data.expense_date}
                    onChange={(value) => setData('expense_date', value)}
                    error={errors.expense_date}
                    required
                />

                <TextField
                    id="paid_to"
                    label="কাকে দেওয়া হলো"
                    value={data.paid_to}
                    onChange={(value) => setData('paid_to', value)}
                    error={errors.paid_to}
                    placeholder="যেমন: পল্লী বিদ্যুৎ"
                />

                {shops.length > 1 && (
                    <SelectField
                        id="shop_id"
                        label="দোকান"
                        value={data.shop_id}
                        onChange={(value) => setData('shop_id', value)}
                        options={shops}
                        error={errors.shop_id}
                        emptyLabel="কোনোটি নয়"
                    />
                )}

                <TextField
                    id="note"
                    label="নোট"
                    value={data.note}
                    onChange={(value) => setData('note', value)}
                    error={errors.note}
                />

                <p className="text-muted-foreground bg-muted/50 rounded-lg border p-3 text-sm">
                    খরচ লেখার পর বদলানো যাবে না। ভুল হলে সংশোধন এন্ট্রি দিতে হবে।
                </p>

                <StickySaveBar processing={processing} cancelHref={route('expenses.index')} />
            </form>
        </AppLayout>
    );
}
