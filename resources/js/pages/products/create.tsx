import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptyProductForm, ProductFormData, ProductFormFields } from './product-form';

interface Props {
    categories: Option[];
    shops: Option[];
}

export default function CreateProduct({ categories, shops }: Props) {
    const { data, setData, post, processing, errors } = useForm<ProductFormData>({
        ...emptyProductForm,
        shop_id: shops.length === 1 ? String(shops[0].value) : '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('products.store'));
    };

    return (
        <MasterDataFormPage title="নতুন পণ্য" resource="products" resourceTitle="পণ্য" onSubmit={submit}>
            <ProductFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                categories={categories}
                shops={shops}
                showOpeningStock
            />
        </MasterDataFormPage>
    );
}
