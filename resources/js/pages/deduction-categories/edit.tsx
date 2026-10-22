import { MasterDataFormPage } from '@/components/master-data-page';
import type { DeductionCategory } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { DeductionCategoryFormData, DeductionCategoryFormFields } from './deduction-category-form';

interface Props {
    category: DeductionCategory;
}

export default function EditDeductionCategory({ category }: Props) {
    const { data, setData, put, processing, errors } = useForm<DeductionCategoryFormData>({
        name: category.name,
        is_active: category.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('deduction-categories.update', category.id));
    };

    return (
        <MasterDataFormPage title={category.name} resource="deduction-categories" resourceTitle="কর্তনের খাত" onSubmit={submit}>
            <DeductionCategoryFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
