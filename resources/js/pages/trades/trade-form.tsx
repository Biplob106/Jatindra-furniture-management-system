import { TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface TradeFormData {
    [key: string]: string | boolean;
    name: string;
    is_active: boolean;
}

export const emptyTradeForm: TradeFormData = {
    name: '',
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

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">সক্রিয়</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('trades.index')} />
        </>
    );
}
