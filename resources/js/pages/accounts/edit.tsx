import { Option, SelectField, TextField } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/number-format';
import type { Account } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    account: Account;
    types: Option[];
    shops: Option[];
    canDeposit: boolean;
}

interface AccountForm {
    [key: string]: string | boolean;
    name: string;
    type: string;
    account_no: string;
    shop_id: string;
    is_active: boolean;
}

export default function EditAccount({ account, types, shops, canDeposit }: Props) {
    const { data, setData, put, processing, errors } = useForm<AccountForm>({
        name: account.name,
        type: account.type,
        account_no: account.account_no ?? '',
        shop_id: account.shop_id ? String(account.shop_id) : '',
        is_active: account.is_active,
    });

    const deposit = useForm({
        amount: '',
        txn_date: new Date().toISOString().slice(0, 10),
        note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('accounts.update', account.id));
    };

    const submitDeposit = () => {
        deposit.post(route('accounts.deposit', account.id), {
            preserveScroll: true,
            onSuccess: () => deposit.reset('amount', 'note'),
        });
    };

    return (
        <MasterDataFormPage title={account.name} resource="accounts" resourceTitle="ক্যাশ ও ব্যাংক" onSubmit={submit}>
            <TextField id="name" label="হিসাবের নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required />

            <SelectField id="type" label="ধরন" value={data.type} onChange={(v) => setData('type', v)} options={types} error={errors.type} required />

            <TextField
                id="account_no"
                label="হিসাব নম্বর"
                numeric
                value={data.account_no}
                onChange={(v) => setData('account_no', v)}
                error={errors.account_no}
            />

            <SelectField
                id="shop_id"
                label="দোকান"
                value={data.shop_id}
                onChange={(v) => setData('shop_id', v)}
                options={shops}
                error={errors.shop_id}
                emptyLabel="সব দোকান"
            />

            <div className="bg-muted/50 rounded-lg border p-4">
                <p className="text-muted-foreground text-sm">বর্তমান জমা</p>
                <p className="text-xl font-semibold">৳ {formatMoney(account.current_balance)}</p>
                <p className="text-muted-foreground mt-1 text-sm">লেনদেন থেকে হিসাব হয়, এখান থেকে বদলানো যায় না।</p>
            </div>

            {canDeposit && (
                <div className="flex flex-col gap-4 rounded-lg border p-4">
                    <div>
                        <p className="font-medium">টাকা জমা দিন</p>
                        <p className="text-muted-foreground text-sm">হাতে থাকা ক্যাশ এই হিসাবে ঢোকান, যাতে বেতন ও অগ্রিম দেওয়া যায়।</p>
                    </div>

                    <TextField
                        id="deposit_amount"
                        label="টাকার পরিমাণ"
                        type="number"
                        numeric
                        value={deposit.data.amount}
                        onChange={(v) => deposit.setData('amount', v)}
                        error={deposit.errors.amount}
                    />

                    <TextField
                        id="deposit_date"
                        label="তারিখ"
                        type="date"
                        value={deposit.data.txn_date}
                        onChange={(v) => deposit.setData('txn_date', v)}
                        error={deposit.errors.txn_date}
                    />

                    <TextField
                        id="deposit_note"
                        label="নোট"
                        value={deposit.data.note}
                        onChange={(v) => deposit.setData('note', v)}
                        error={deposit.errors.note}
                    />

                    <Button type="button" onClick={submitDeposit} disabled={deposit.processing} className="h-12 text-base">
                        জমা দিন
                    </Button>
                </div>
            )}

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">সক্রিয়</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('accounts.index')} />
        </MasterDataFormPage>
    );
}
