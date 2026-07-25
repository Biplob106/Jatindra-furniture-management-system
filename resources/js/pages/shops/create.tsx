import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptyShopForm, ShopFormData, ShopFormFields } from './shop-form';

export default function CreateShop() {
    const { data, setData, post, processing, errors } = useForm<ShopFormData>(emptyShopForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('shops.store'));
    };

    return (
        <MasterDataFormPage title="নতুন দোকান" resource="shops" resourceTitle="দোকান" onSubmit={submit}>
            <ShopFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
