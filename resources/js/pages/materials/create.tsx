import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { emptyMaterialForm, MaterialFormData, MaterialFormFields } from './material-form';

interface Props {
    categories: Option[];
    units: Option[];
}

export default function CreateMaterial({ categories, units }: Props) {
    const { data, setData, post, processing, errors } = useForm<MaterialFormData>(emptyMaterialForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('materials.store'));
    };

    return (
        <MasterDataFormPage title="নতুন মালামাল" resource="materials" resourceTitle="মালামাল" onSubmit={submit}>
            <MaterialFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                categories={categories}
                units={units}
                showOpeningStock
            />
        </MasterDataFormPage>
    );
}
