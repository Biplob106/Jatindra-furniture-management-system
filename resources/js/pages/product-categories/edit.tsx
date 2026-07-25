import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import type { ProductCategory } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { ProductCategoryFormData, ProductCategoryFormFields } from './product-category-form';

interface Props {
    category: ProductCategory;
    parents: Option[];
}

export default function EditProductCategory({ category, parents }: Props) {
    const { data, setData, put, processing, errors } = useForm<ProductCategoryFormData>({
        name: category.name,
        parent_id: category.parent_id ? String(category.parent_id) : '',
        is_active: category.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('product-categories.update', category.id));
    };

    return (
        <MasterDataFormPage title={category.name} resource="product-categories" resourceTitle="পণ্যের ক্যাটাগরি" onSubmit={submit}>
            <ProductCategoryFormFields data={data} setData={setData} errors={errors} processing={processing} parents={parents} />
        </MasterDataFormPage>
    );
}
