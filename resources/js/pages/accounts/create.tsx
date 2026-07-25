import { Option, SelectField, TextField } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    types: Option[];
    shops: Option[];
}

interface AccountForm {
    [key: string]: string | boolean;
    name: string;
    type: string;
    account_no: string;
    shop_id: string;
    opening_balance: string;
    is_active: boolean;
}

export default function CreateAccount({ types, shops }: Props) {
    const { data, setData, post, processing, errors } = useForm<AccountForm>({
        name: '',
        type: '',
        account_no: '',
        shop_id: '',
        opening_balance: '0',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('accounts.store'));
    };

    return (
        <MasterDataFormPage
            title="নতুন হিসাব"
            subtitle="যেখানে টাকা রাখা হয়"
            resource="accounts"
            resourceTitle="হিসাব"
            onSubmit={submit}
        >
            <TextField
                id="name"
                label="হিসাবের নাম"
                value={data.name}
                onChange={(v) => setData('name', v)}
                error={errors.name}
                required
                autoFocus
                placeholder="যেমন: ক্যাশ বাক্স"
            />

            <SelectField id="type" label="ধরন" value={data.type} onChange={(v) => setData('type', v)} options={types} error={errors.type} required />

            <TextField
                id="account_no"
                label="হিসাব নম্বর"
                numeric
                value={data.account_no}
                onChange={(v) => setData('account_no', v)}
                error={errors.account_no}
                hint="বিকাশ বা ব্যাংক হলে নম্বর দিন"
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

            <TextField
                id="opening_balance"
                label="শুরুর জমা"
                type="number"
                numeric
                value={data.opening_balance}
                onChange={(v) => setData('opening_balance', v)}
                error={errors.opening_balance}
                required
                hint="এখন এই হিসাবে যত টাকা আছে। পরে আর বদলানো যাবে না।"
            />

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">সক্রিয়</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('accounts.index')} />
        </MasterDataFormPage>
    );
}
