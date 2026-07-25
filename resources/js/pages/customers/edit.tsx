import { toBengaliDigits } from '@/components/data-table';
import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import type { Customer } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { CustomerFormData, CustomerFormFields } from './customer-form';

interface Props {
    customer: Customer;
    types: Option[];
}

export default function EditCustomer({ customer, types }: Props) {
    const { data, setData, put, processing, errors } = useForm<CustomerFormData>({
        name: customer.name,
        phone: customer.phone,
        alt_phone: customer.alt_phone ?? '',
        address: customer.address ?? '',
        area: customer.area ?? '',
        customer_type: customer.customer_type,
        opening_due: customer.opening_due,
        note: customer.note ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('customers.update', customer.id));
    };

    return (
        <MasterDataFormPage title={customer.name} resource="customers" resourceTitle="কাস্টমার" onSubmit={submit}>
            {Number(customer.opening_due) > 0 && (
                <div className="bg-muted/50 rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">আগের বকেয়া</p>
                    <p className="text-xl font-semibold">৳ {toBengaliDigits(customer.opening_due)}</p>
                </div>
            )}

            <CustomerFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                types={types}
                showOpeningDue={false}
            />
        </MasterDataFormPage>
    );
}
