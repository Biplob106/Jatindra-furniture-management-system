import { Option, SelectField, TextField } from '@/components/form-field';
import { StickySaveBar } from '@/components/sticky-save-bar';

export interface ProductFormData {
    [key: string]: string;
    sku: string;
    name: string;
    category_id: string;
    description: string;
    wood_type: string;
    size_label: string;
    cost_price: string;
    sale_price: string;
    min_stock: string;
    opening_stock: string;
    shop_id: string;
    is_active: string;
}

export const emptyProductForm: ProductFormData = {
    sku: '',
    name: '',
    category_id: '',
    description: '',
    wood_type: '',
    size_label: '',
    cost_price: '0',
    sale_price: '0',
    min_stock: '0',
    opening_stock: '0',
    shop_id: '',
    is_active: '1',
};

interface Props {
    data: ProductFormData;
    setData: (key: string, value: string) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    categories: Option[];
    shops: Option[];
    /** Stock on hand is only typed on day one. After that it comes from movements. */
    showOpeningStock: boolean;
}

const activeOptions: Option[] = [
    { value: '1', label: 'সচল' },
    { value: '0', label: 'বন্ধ' },
];

export function ProductFormFields({ data, setData, errors, processing, categories, shops, showOpeningStock }: Props) {
    const margin = Number(data.sale_price || 0) - Number(data.cost_price || 0);

    return (
        <>
            <TextField id="name" label="নাম" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required autoFocus />

            <TextField
                id="sku"
                label="পণ্যের কোড"
                value={data.sku}
                onChange={(v) => setData('sku', v)}
                error={errors.sku}
                required
                hint="দোকানে ট্যাগে যে কোড লেখা থাকে"
            />

            <SelectField
                id="category_id"
                label="ক্যাটাগরি"
                value={data.category_id}
                onChange={(v) => setData('category_id', v)}
                options={categories}
                error={errors.category_id}
            />

            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    id="wood_type"
                    label="কাঠের ধরন"
                    value={data.wood_type}
                    onChange={(v) => setData('wood_type', v)}
                    error={errors.wood_type}
                />

                <TextField
                    id="size_label"
                    label="মাপ"
                    value={data.size_label}
                    onChange={(v) => setData('size_label', v)}
                    error={errors.size_label}
                    placeholder="৬ × ৩ ফুট"
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                    id="cost_price"
                    label="খরচ দর"
                    type="number"
                    numeric
                    value={data.cost_price}
                    onChange={(v) => setData('cost_price', v)}
                    error={errors.cost_price}
                    required
                    hint="তৈরি বা কেনা পড়েছে যত"
                />

                <TextField
                    id="sale_price"
                    label="বিক্রয় দর"
                    type="number"
                    numeric
                    value={data.sale_price}
                    onChange={(v) => setData('sale_price', v)}
                    error={errors.sale_price}
                    required
                />
            </div>

            {/* The gap between the two is the margin, so it is shown while it is
                being typed rather than discovered later on a report. */}
            {margin !== 0 && (
                <p className={margin < 0 ? 'text-destructive text-sm' : 'text-muted-foreground text-sm'}>
                    {margin < 0 ? 'বিক্রয় দর খরচের চেয়ে কম' : `প্রতি পিসে লাভ ৳ ${margin.toFixed(2)}`}
                </p>
            )}

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
                <TextField
                    id="opening_stock"
                    label="এখন যত আছে"
                    type="number"
                    numeric
                    value={data.opening_stock}
                    onChange={(v) => setData('opening_stock', v)}
                    error={errors.opening_stock}
                    required
                    hint="আজ দোকানে যত পিস আছে। এরপর বিক্রি থেকে হিসাব হবে।"
                />
            )}

            {shops.length > 1 && (
                <SelectField
                    id="shop_id"
                    label="দোকান"
                    value={data.shop_id}
                    onChange={(v) => setData('shop_id', v)}
                    options={shops}
                    error={errors.shop_id}
                />
            )}

            <TextField
                id="description"
                label="বিবরণ"
                value={data.description}
                onChange={(v) => setData('description', v)}
                error={errors.description}
            />

            <SelectField
                id="is_active"
                label="অবস্থা"
                value={data.is_active}
                onChange={(v) => setData('is_active', v)}
                options={activeOptions}
                error={errors.is_active}
                required
            />

            <StickySaveBar processing={processing} cancelHref={route('products.index')} />
        </>
    );
}
