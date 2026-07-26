import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';

export interface SupplierFormData {
    [key: string]: string;
    name: string;
    business_name: string;
    phone: string;
    address: string;
    supplier_type: string;
    opening_due: string;
    credit_limit: string;
    default_credit_days: string;
    is_active: string;
}

export const emptySupplierForm: SupplierFormData = {
    name: '',
    business_name: '',
    phone: '',
    address: '',
    supplier_type: 'wood',
    opening_due: '0',
    credit_limit: '0',
    default_credit_days: '0',
    is_active: '1',
};

interface Props {
    data: SupplierFormData;
    setData: (key: string, value: string) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    types: Option[];
    /** The opening due is a day-one figure, hidden once the supplier exists. */
    showOpeningDue: boolean;
}

const activeOptions: Option[] = [
    { value: '1', label: 'সচল' },
    { value: '0', label: 'বন্ধ' },
];

export function SupplierFormFields({ data, setData, errors, processing, types, showOpeningDue }: Props) {
    return (
        <>
            <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

            <TextField
                id="business_name"
                label="দোকানের নাম"
                value={data.business_name}
                onChange={(v) => setData('business_name', v)}
                error={errors.business_name}
            />

            <TextField
                id="phone"
                label="মোবাইল নম্বর"
                type="tel"
                numeric
                value={data.phone}
                onChange={(v) => setData('phone', v)}
                error={errors.phone}
                placeholder="01XXXXXXXXX"
            />

            <SelectField
                id="supplier_type"
                label="কী সরবরাহ করেন"
                value={data.supplier_type}
                onChange={(v) => setData('supplier_type', v)}
                options={types}
                error={errors.supplier_type}
                required
            />

            <TextField id="address" label="ঠিকানা" value={data.address} onChange={(v) => setData('address', v)} error={errors.address} />

            <TextField
                id="default_credit_days"
                label="বাকির মেয়াদ (দিন)"
                type="number"
                numeric
                value={data.default_credit_days}
                onChange={(v) => setData('default_credit_days', v)}
                error={errors.default_credit_days}
                required
                hint="বাকিতে কেনা মাল কত দিনে শোধ করার কথা"
            />

            <TextField
                id="credit_limit"
                label="বাকির সীমা"
                type="number"
                numeric
                value={data.credit_limit}
                onChange={(v) => setData('credit_limit', v)}
                error={errors.credit_limit}
                required
            />

            {showOpeningDue && (
                <TextField
                    id="opening_due"
                    label="খাতার আগের বকেয়া"
                    type="number"
                    numeric
                    value={data.opening_due}
                    onChange={(v) => setData('opening_due', v)}
                    error={errors.opening_due}
                    required
                    hint="আজ পর্যন্ত যত টাকা পাওনা আছে। পরে কেনাকাটা থেকে হিসাব হবে।"
                />
            )}

            <SelectField
                id="is_active"
                label="অবস্থা"
                value={data.is_active}
                onChange={(v) => setData('is_active', v)}
                options={activeOptions}
                error={errors.is_active}
                required
            />

            <StickySaveBar processing={processing} cancelHref={route('suppliers.index')} />
        </>
    );
}
