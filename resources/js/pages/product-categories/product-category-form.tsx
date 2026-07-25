import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

export interface ProductCategoryFormData {
    [key: string]: string | boolean;
    name: string;
    parent_id: string;
    is_active: boolean;
}

export const emptyProductCategoryForm: ProductCategoryFormData = {
    name: '',
    parent_id: '',
    is_active: true,
};

interface Props {
    data: ProductCategoryFormData;
    setData: (key: string, value: string | boolean) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    parents: Option[];
}

export function ProductCategoryFormFields({ data, setData, errors, processing, parents }: Props) {
    return (
        <>
            <TextField
                id="name"
                label="ক্যাটাগরির নাম"
                value={data.name}
                onChange={(v) => setData('name', v)}
                error={errors.name}
                required
                autoFocus
                placeholder="যেমন: খাট"
            />

            <SelectField
                id="parent_id"
                label="প্যারেন্ট ক্যাটাগরি"
                value={data.parent_id}
                onChange={(v) => setData('parent_id', v)}
                options={parents}
                error={errors.parent_id}
                emptyLabel="কোনোটি নয়"
                hint="খালি রাখলে এটি একটি প্রধান ক্যাটাগরি হবে"
            />

            <div className="flex items-center gap-3">
                <Checkbox id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
                <Label htmlFor="is_active">সক্রিয়</Label>
            </div>

            <StickySaveBar processing={processing} cancelHref={route('product-categories.index')} />
        </>
    );
}
