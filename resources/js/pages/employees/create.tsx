import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { EmployeeFormData, EmployeeFormFields, TradeOption } from './employee-form';

interface Props {
    wageTypes: Option[];
    trades: TradeOption[];
    shops: Option[];
    nextCode: string;
}

export default function CreateEmployee({ wageTypes, trades, shops, nextCode }: Props) {
    const { data, setData, post, processing, errors } = useForm<EmployeeFormData>({
        employee_code: nextCode,
        name: '',
        phone: '',
        address: '',
        nid_no: '',
        trade_id: '',
        shop_id: '',
        wage_type: 'daily',
        daily_rate: '0',
        monthly_salary: '0',
        joining_date: '',
        guarantor_name: '',
        guarantor_phone: '',
        opening_advance: '0',
        is_active: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('employees.store'));
    };

    return (
        <MasterDataFormPage title="নতুন কর্মী" resource="employees" resourceTitle="কর্মী" onSubmit={submit}>
            <EmployeeFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                wageTypes={wageTypes}
                trades={trades}
                shops={shops}
                showOpeningAdvance
            />
        </MasterDataFormPage>
    );
}
