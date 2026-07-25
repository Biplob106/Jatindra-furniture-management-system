import { TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface ExpenseCategoryFormData {
    [key: string]: string | boolean;
    name: string;
    is_recurring: boolean;
    is_active: boolean;
}

export const emptyExpenseCategoryForm: ExpenseCategoryFormData = {
    name: '',
    is_recurring: false,
    is_active: true,
};

interface Props {
    data: ExpenseCategoryFormData;
    setData: (key: string, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
}

export function ExpenseCategoryFormFields({ data, setData, errors, processing }: Props) {
    return (
        <>
            <TextField
                id="name"
                label="খাতের নাম"
                value={data.name}
                onChange={(v) => setData('name', v)}
                error={errors.name}
                required
                autoFocus
                placeholder="যেমন: দোকান ভাড়া"
            />

            <div className="flex items-center gap-3">
                <Checkbox id="is_recurring" checked={data.is_recurring} onCheckedChange={(checked) => setData('is_recurring', checked === true)} />
                <Label htmlFor="is_recurring">প্রতি মাসে একই খরচ</Label>
            </div>

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">সক্রিয়</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('expense-categories.index')} />
        </>
    );
}
