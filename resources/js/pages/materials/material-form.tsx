import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';

export interface MaterialFormData {
    [key: string]: string;
    name: string;
    category: string;
    unit: string;
    min_stock: string;
    opening_stock: string;
    opening_cost: string;
    is_active: string;
}

export const emptyMaterialForm: MaterialFormData = {
    name: '',
    category: 'wood',
    unit: 'cft',
    min_stock: '0',
    opening_stock: '0',
    opening_cost: '0',
    is_active: '1',
};

interface Props {
    data: MaterialFormData;
    setData: (key: string, value: string) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    categories: Option[];
    units: Option[];
    /** Stock on hand is only typed on day one. After that it comes from movements. */
    showOpeningStock: boolean;
}

const activeOptions: Option[] = [
    { value: '1', label: 'সচল' },
    { value: '0', label: 'বন্ধ' },
];

export function MaterialFormFields({ data, setData, errors, processing, categories, units, showOpeningStock }: Props) {
    return (
        <>
            <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

            <SelectField
                id="category"
                label="ধরন"
                value={data.category}
                onChange={(v) => setData('category', v)}
                options={categories}
                error={errors.category}
                required
            />

            <SelectField
                id="unit"
                label="মাপের একক"
                value={data.unit}
                onChange={(v) => setData('unit', v)}
                options={units}
                error={errors.unit}
                required
                hint="কাঠ ঘনফুটে, বোর্ড বর্গফুটে, হার্ডওয়্যার পিসে"
            />

            <TextField
                id="min_stock"
                label="সর্বনিম্ন মজুদ"
                type="number"
                numeric
                value={data.min_stock}
                onChange={(v) => setData('min_stock', v)}
                error={errors.min_stock}
                required
                hint="এর নিচে নামলে তালিকায় সতর্কবার্তা দেখাবে। ০ দিলে কিছু দেখাবে না।"
            />

            {showOpeningStock && (
                <>
                    <TextField
                        id="opening_stock"
                        label="এখন যত মজুদ আছে"
                        type="number"
                        numeric
                        value={data.opening_stock}
                        onChange={(v) => setData('opening_stock', v)}
                        error={errors.opening_stock}
                        required
                        hint="আজ গুদামে যত আছে। এরপর কেনাকাটা থেকে হিসাব হবে।"
                    />

                    <TextField
                        id="opening_cost"
                        label="একক দর"
                        type="number"
                        numeric
                        value={data.opening_cost}
                        onChange={(v) => setData('opening_cost', v)}
                        error={errors.opening_cost}
                        required
                        hint="যে দরে কেনা হয়েছিল"
                    />
                </>
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

            <StickySaveBar processing={processing} cancelHref={route('materials.index')} />
        </>
    );
}
