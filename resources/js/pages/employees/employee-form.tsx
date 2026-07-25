import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface TradeOption extends Option {
    defaultDailyRate: string;
}

export interface EmployeeFormData {
    [key: string]: string | boolean;
    employee_code: string;
    name: string;
    phone: string;
    address: string;
    nid_no: string;
    trade_id: string;
    shop_id: string;
    wage_type: string;
    daily_rate: string;
    monthly_salary: string;
    joining_date: string;
    guarantor_name: string;
    guarantor_phone: string;
    opening_advance: string;
    is_active: boolean;
}

interface Props {
    data: EmployeeFormData;
    setData: (key: string, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    wageTypes: Option[];
    trades: TradeOption[];
    shops: Option[];
    showOpeningAdvance: boolean;
}

export function EmployeeFormFields({ data, setData, errors, processing, wageTypes, trades, shops, showOpeningAdvance }: Props) {
    /**
     * Picking a trade fills in its default rate, but only when the field is
     * still empty or zero, so an edited rate is never overwritten.
     */
    const onTradeChange = (value: string) => {
        setData('trade_id', value);

        const trade = trades.find((t) => String(t.value) === value);

        if (trade && data.wage_type === 'daily' && (data.daily_rate === '' || Number(data.daily_rate) === 0)) {
            setData('daily_rate', trade.defaultDailyRate);
        }
    };

    return (
        <>
            <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

            <TextField
                id="employee_code"
                label="কর্মী কোড"
                value={data.employee_code}
                onChange={(v) => setData('employee_code', v)}
                error={errors.employee_code}
                required
            />

            <TextField
                id="phone"
                label="মোবাইল নম্বর"
                type="tel"
                numeric
                value={data.phone}
                onChange={(v) => setData('phone', v)}
                error={errors.phone}
            />

            <SelectField
                id="trade_id"
                label="কাজের ধরন"
                value={data.trade_id}
                onChange={onTradeChange}
                options={trades}
                error={errors.trade_id}
                emptyLabel="নির্ধারিত নয়"
            />

            <SelectField
                id="wage_type"
                label="মজুরির ধরন"
                value={data.wage_type}
                onChange={(v) => setData('wage_type', v)}
                options={wageTypes}
                error={errors.wage_type}
                required
            />

            {data.wage_type === 'daily' && (
                <TextField
                    id="daily_rate"
                    label="দৈনিক হাজিরা"
                    type="number"
                    numeric
                    value={data.daily_rate}
                    onChange={(v) => setData('daily_rate', v)}
                    error={errors.daily_rate}
                    required
                    hint="উপস্থিত থাকলে এই টাকা জমা হবে, অর্ধদিবসে অর্ধেক"
                />
            )}

            {data.wage_type === 'monthly' && (
                <TextField
                    id="monthly_salary"
                    label="মাসিক বেতন"
                    type="number"
                    numeric
                    value={data.monthly_salary}
                    onChange={(v) => setData('monthly_salary', v)}
                    error={errors.monthly_salary}
                    required
                    hint="মাসের শেষ দিনে একবার জমা হবে"
                />
            )}

            {data.wage_type === 'piece' && (
                <p className="text-muted-foreground bg-muted/50 rounded-lg border p-4 text-sm">
                    কাজ চুক্তিতে অর্ডারের কাজ শেষ হলে চুক্তির টাকা জমা হবে। এখানে কোনো হার দিতে হবে না।
                </p>
            )}

            <SelectField
                id="shop_id"
                label="দোকান"
                value={data.shop_id}
                onChange={(v) => setData('shop_id', v)}
                options={shops}
                error={errors.shop_id}
                emptyLabel="সব দোকান"
            />

            <TextField
                id="joining_date"
                label="যোগদানের তারিখ"
                type="date"
                value={data.joining_date}
                onChange={(v) => setData('joining_date', v)}
                error={errors.joining_date}
            />

            <TextField id="nid_no" label="এনআইডি নম্বর" numeric value={data.nid_no} onChange={(v) => setData('nid_no', v)} error={errors.nid_no} />

            <TextField id="address" label="ঠিকানা" value={data.address} onChange={(v) => setData('address', v)} error={errors.address} />

            <TextField
                id="guarantor_name"
                label="জামিনদারের নাম"
                value={data.guarantor_name}
                onChange={(v) => setData('guarantor_name', v)}
                error={errors.guarantor_name}
            />

            <TextField
                id="guarantor_phone"
                label="জামিনদারের মোবাইল"
                type="tel"
                numeric
                value={data.guarantor_phone}
                onChange={(v) => setData('guarantor_phone', v)}
                error={errors.guarantor_phone}
            />

            {showOpeningAdvance && (
                <TextField
                    id="opening_advance"
                    label="আগের অগ্রিম"
                    type="number"
                    numeric
                    value={data.opening_advance}
                    onChange={(v) => setData('opening_advance', v)}
                    error={errors.opening_advance}
                    required
                    hint="খাতায় কর্মী যত টাকা অগ্রিম নিয়েছে"
                />
            )}

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">কর্মী এখনো কাজ করছে</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('employees.index')} />
        </>
    );
}
