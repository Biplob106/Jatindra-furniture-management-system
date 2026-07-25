import { TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface TradeFormData {
    [key: string]: string | boolean;
    name: string;
    default_daily_rate: string;
    is_active: boolean;
}

export const emptyTradeForm: TradeFormData = {
    name: '',
    default_daily_rate: '0',
    is_active: true,
};

interface Props {
    data: TradeFormData;
    setData: (key: string, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
}

export function TradeFormFields({ data, setData, errors, processing }: Props) {
    return (
        <>
            <TextField
                id="name"
                label="কাজের ধরন"
                value={data.name}
                onChange={(v) => setData('name', v)}
                error={errors.name}
                required
                autoFocus
                placeholder="যেমন: বার্নিশ"
            />

            <TextField
                id="default_daily_rate"
                label="দৈনিক হার"
                type="number"
                numeric
                value={data.default_daily_rate}
                onChange={(v) => setData('default_daily_rate', v)}
                error={errors.default_daily_rate}
                required
                hint="নতুন কর্মী যোগ করার সময় এই হার আগে থেকে বসানো থাকবে"
            />

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">সক্রিয়</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('trades.index')} />
        </>
    );
}
