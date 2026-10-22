import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { DeductionCategoryFormData, DeductionCategoryFormFields, emptyDeductionCategoryForm } from './deduction-category-form';

export default function CreateDeductionCategory() {
    const { data, setData, post, processing, errors } = useForm<DeductionCategoryFormData>(emptyDeductionCategoryForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('deduction-categories.store'));
    };

    return (
        <MasterDataFormPage title="নতুন কর্তনের খাত" resource="deduction-categories" resourceTitle="কর্তনের খাত" onSubmit={submit}>
            <DeductionCategoryFormFields data={data} setData={setData} errors={errors} processing={processing} />
        </MasterDataFormPage>
    );
}
