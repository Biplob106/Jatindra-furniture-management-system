import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptyProductCategoryForm, ProductCategoryFormData, ProductCategoryFormFields } from './product-category-form';

interface Props {
    parents: Option[];
}

export default function CreateProductCategory({ parents }: Props) {
    const { data, setData, post, processing, errors } = useForm<ProductCategoryFormData>(emptyProductCategoryForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('product-categories.store'));
    };

    return (
        <MasterDataFormPage title="নতুন ক্যাটাগরি" resource="product-categories" resourceTitle="পণ্যের ক্যাটাগরি" onSubmit={submit}>
            <ProductCategoryFormFields data={data} setData={setData} errors={errors} processing={processing} parents={parents} />
        </MasterDataFormPage>
    );
}
