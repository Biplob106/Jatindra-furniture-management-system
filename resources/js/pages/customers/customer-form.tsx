import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';

export interface CustomerFormData {
    [key: string]: string;
    name: string;
    phone: string;
    alt_phone: string;
    address: string;
    area: string;
    customer_type: string;
    opening_due: string;
    note: string;
}

export const emptyCustomerForm: CustomerFormData = {
    name: '',
    phone: '',
    alt_phone: '',
    address: '',
    area: '',
    customer_type: 'retail',
    opening_due: '0',
    note: '',
};

interface Props {
    data: CustomerFormData;
    setData: (key: string, value: string) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    types: Option[];
    /** Opening due is a day-one figure and is hidden once the customer exists. */
    showOpeningDue: boolean;
}

export function CustomerFormFields({ data, setData, errors, processing, types, showOpeningDue }: Props) {
    return (
        <>
            <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

            <TextField
                id="phone"
                label="মোবাইল নম্বর"
                type="tel"
                numeric
                value={data.phone}
                onChange={(v) => setData('phone', v)}
                error={errors.phone}
                required
                placeholder="01XXXXXXXXX"
                hint="এই নম্বর দিয়েই কাস্টমার খোঁজা হবে"
            />

            <TextField
                id="alt_phone"
                label="বিকল্প মোবাইল"
                type="tel"
                numeric
                value={data.alt_phone}
                onChange={(v) => setData('alt_phone', v)}
                error={errors.alt_phone}
            />

            <SelectField
                id="customer_type"
                label="ধরন"
                value={data.customer_type}
                onChange={(v) => setData('customer_type', v)}
                options={types}
                error={errors.customer_type}
                required
            />

            <TextField id="area" label="এলাকা" value={data.area} onChange={(v) => setData('area', v)} error={errors.area} />

            <TextField id="address" label="ঠিকানা" value={data.address} onChange={(v) => setData('address', v)} error={errors.address} />

            {showOpeningDue && (
                <TextField
                    id="opening_due"
                    label="আগের বকেয়া"
                    type="number"
                    numeric
                    value={data.opening_due}
                    onChange={(v) => setData('opening_due', v)}
                    error={errors.opening_due}
                    required
                    hint="খাতায় যত বকেয়া আছে। পরে অর্ডার থেকে বকেয়া হিসাব হবে।"
                />
            )}

            <TextField id="note" label="নোট" value={data.note} onChange={(v) => setData('note', v)} error={errors.note} />

            <StickySaveBar processing={processing} cancelHref={route('customers.index')} />
        </>
    );
}
