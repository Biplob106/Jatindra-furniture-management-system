import { MasterDataFormPage } from '@/components/master-data-page';
import type { Shop } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { ShopFormData, ShopFormFields } from './shop-form';

interface Props {
    shop: Shop;
}

export default function EditShop({ shop }: Props) {
    const { data, setData, put, processing, errors } = useForm<ShopFormData>({
        name: shop.name,
        address: shop.address ?? '',
        phone: shop.phone ?? '',
        monthly_rent: shop.monthly_rent,
        rent_due_day: shop.rent_due_day ? String(shop.rent_due_day) : '',
        landlord_name: shop.landlord_name ?? '',
        landlord_phone: shop.landlord_phone ?? '',
        electricity_meter_no: shop.electricity_meter_no ?? '',
        is_active: shop.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('shops.update', shop.id));
    };

    return (
        <MasterDataFormPage title={shop.name} subtitle="দোকানের তথ্য বদলান" resource="shops" resourceTitle="দোকান" onSubmit={submit}>
            <ShopFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
