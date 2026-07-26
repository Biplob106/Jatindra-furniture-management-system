import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptySupplierForm, SupplierFormData, SupplierFormFields } from './supplier-form';

interface Props {
    types: Option[];
}

export default function CreateSupplier({ types }: Props) {
    const { data, setData, post, processing, errors } = useForm<SupplierFormData>(emptySupplierForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('suppliers.store'));
    };

    return (
        <MasterDataFormPage title="নতুন সরবরাহকারী" resource="suppliers" resourceTitle="সরবরাহকারী" onSubmit={submit}>
            <SupplierFormFields data={data} setData={setData} errors={errors} processing={processing} types={types} showOpeningDue />
        </MasterDataFormPage>
    );
}
