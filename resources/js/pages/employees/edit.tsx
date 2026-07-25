import { toBengaliDigits } from '@/components/data-table';
import { Option } from '@/components/form-field';
import { MasterDataFormPage } from '@/components/master-data-page';
import type { Employee } from '@/types/models';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { EmployeeFormData, EmployeeFormFields, TradeOption } from './employee-form';

interface Props {
    employee: Employee;
    wageTypes: Option[];
    trades: TradeOption[];
    shops: Option[];
}

export default function EditEmployee({ employee, wageTypes, trades, shops }: Props) {
    const { data, setData, put, processing, errors } = useForm<EmployeeFormData>({
        employee_code: employee.employee_code,
        name: employee.name,
        phone: employee.phone ?? '',
        address: employee.address ?? '',
        nid_no: employee.nid_no ?? '',
        trade_id: employee.trade_id ? String(employee.trade_id) : '',
        shop_id: employee.shop_id ? String(employee.shop_id) : '',
        wage_type: employee.wage_type,
        daily_rate: employee.daily_rate,
        monthly_salary: employee.monthly_salary,
        joining_date: employee.joining_date ?? '',
        guarantor_name: employee.guarantor_name ?? '',
        guarantor_phone: employee.guarantor_phone ?? '',
        opening_advance: employee.opening_advance,
        is_active: employee.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('employees.update', employee.id));
    };

    return (
        <MasterDataFormPage title={employee.name} subtitle={employee.employee_code} resource="employees" resourceTitle="কর্মী" onSubmit={submit}>
            {Number(employee.opening_advance) > 0 && (
                <div className="bg-muted/50 rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">আগের অগ্রিম</p>
                    <p className="text-xl font-semibold">৳ {toBengaliDigits(employee.opening_advance)}</p>
                </div>
            )}

            <EmployeeFormFields
                data={data}
                setData={setData}
                errors={errors}
                processing={processing}
                wageTypes={wageTypes}
                trades={trades}
                shops={shops}
                showOpeningAdvance={false}
            />
        </MasterDataFormPage>
    );
}
