import { toBengaliDigits } from '@/components/data-table';
import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import type { Supplier } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { SupplierFormData, SupplierFormFields } from './supplier-form';

interface Props {
    supplier: Supplier;
    types: Option[];
    /** SUM(credit) - SUM(debit). Positive means we owe them. */
    balance: string;
}

export default function EditSupplier({ supplier, types, balance }: Props) {
    const { data, setData, put, processing, errors } = useForm<SupplierFormData>({
        name: supplier.name,
        business_name: supplier.business_name ?? '',
        phone: supplier.phone ?? '',
        address: supplier.address ?? '',
        supplier_type: supplier.supplier_type,
        opening_due: supplier.opening_due,
        credit_limit: supplier.credit_limit,
        default_credit_days: String(supplier.default_credit_days),
        is_active: supplier.is_active ? '1' : '0',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('suppliers.update', supplier.id));
    };

    const owed = Number(balance);

    return (
        <MasterDataFormPage title={supplier.name} resource="suppliers" resourceTitle="সরবরাহকারী" onSubmit={submit}>
            {owed !== 0 && (
                <div className="bg-muted/50 rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">{owed > 0 ? 'এখন বাকি আছে' : 'আগাম দেওয়া আছে'}</p>
                    <p className="text-xl font-semibold">৳ {toBengaliDigits(balance.replace('-', ''))}</p>
                </div>
            )}

            <SupplierFormFields
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
