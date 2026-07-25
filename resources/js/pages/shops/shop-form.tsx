import { TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface ShopFormData {
    [key: string]: string | boolean;
    name: string;
    address: string;
    phone: string;
    monthly_rent: string;
    rent_due_day: string;
    landlord_name: string;
    landlord_phone: string;
    electricity_meter_no: string;
    is_active: boolean;
}

export const emptyShopForm: ShopFormData = {
    name: '',
    address: '',
    phone: '',
    monthly_rent: '0',
    rent_due_day: '',
    landlord_name: '',
    landlord_phone: '',
    electricity_meter_no: '',
    is_active: true,
};

interface Props {
    data: ShopFormData;
    setData: (key: string, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
}

/**
 * Shared by create and edit so the two screens cannot drift apart.
 */
export function ShopFormFields({ data, setData, errors, processing }: Props) {
    return (
        <>
            <TextField id="name" label="দোকানের নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

            <TextField
                id="phone"
                label="মোবাইল নম্বর"
                type="tel"
                numeric
                value={data.phone}
                onChange={(v) => setData('phone', v)}
                error={errors.phone}
            />

            <TextField id="address" label="ঠিকানা" value={data.address} onChange={(v) => setData('address', v)} error={errors.address} />

            <TextField
                id="monthly_rent"
                label="মাসিক ভাড়া"
                type="number"
                numeric
                value={data.monthly_rent}
                onChange={(v) => setData('monthly_rent', v)}
                error={errors.monthly_rent}
                required
            />

            <TextField
                id="rent_due_day"
                label="ভাড়া দেওয়ার তারিখ"
                type="number"
                numeric
                value={data.rent_due_day}
                onChange={(v) => setData('rent_due_day', v)}
                error={errors.rent_due_day}
                hint="মাসের কত তারিখে ভাড়া দিতে হয় (১-৩১)"
            />

            <TextField
                id="landlord_name"
                label="বাড়িওয়ালার নাম"
                value={data.landlord_name}
                onChange={(v) => setData('landlord_name', v)}
                error={errors.landlord_name}
            />

            <TextField
                id="landlord_phone"
                label="বাড়িওয়ালার মোবাইল"
                type="tel"
                numeric
                value={data.landlord_phone}
                onChange={(v) => setData('landlord_phone', v)}
                error={errors.landlord_phone}
            />

            <TextField
                id="electricity_meter_no"
                label="বিদ্যুৎ মিটার নম্বর"
                numeric
                value={data.electricity_meter_no}
                onChange={(v) => setData('electricity_meter_no', v)}
                error={errors.electricity_meter_no}
            />

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">দোকান চালু আছে</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('shops.index')} />
        </>
    );
}
