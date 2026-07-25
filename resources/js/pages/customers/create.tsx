import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { CustomerFormData, CustomerFormFields, emptyCustomerForm } from './customer-form';

interface Props {
    types: Option[];
}

export default function CreateCustomer({ types }: Props) {
    const { data, setData, post, processing, errors } = useForm<CustomerFormData>(emptyCustomerForm);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('customers.store'));
    };

    return (
        <MasterDataFormPage title="নতুন কাস্টমার" resource="customers" resourceTitle="কাস্টমার" onSubmit={submit}>
            <CustomerFormFields data={data} setData={setData} errors={errors} processing={processing} types={types} showOpeningDue />
        </MasterDataFormPage>
    );
}
