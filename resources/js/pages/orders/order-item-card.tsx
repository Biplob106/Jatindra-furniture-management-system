import { ChoiceChips } from '@/components/choice-chips';
import { toBengaliDigits } from '@/components/data-table';
import { Field, Option, SelectField, TextField } from '@/components/form-field';
import { ImagePicker, type OrderImage, useObjectUrls } from '@/components/image-picker';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import { ChevronDown, Minus, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface ItemForm {
    [key: string]: string | number | undefined | OrderImage[] | File[] | number[];
    /** Browser-only identity. Never sent; keeps images with their card. */
    client_key: string;
    id?: number;
    category_id: string;
    item_name: string;
    description: string;
    wood_type: string;
    design_no: string;
    length: string;
    width: string;
    height: string;
    dimension_unit: string;
    polish_type: string;
    quantity: string;
    unit_price: string;
    target_date: string;
    remarks: string;
    photos: OrderImage[];
    designs: OrderImage[];
    new_photos: File[];
    new_designs: File[];
    remove_media: number[];
}

export const emptyItem = (): ItemForm => ({
    client_key: crypto.randomUUID(),
    category_id: '',
    item_name: '',
    description: '',
    wood_type: '',
    design_no: '',
    length: '',
    width: '',
    height: '',
    dimension_unit: 'inch',
    polish_type: '',
    quantity: '1',
    unit_price: '',
    target_date: '',
    remarks: '',
    photos: [],
    designs: [],
    new_photos: [],
    new_designs: [],
    remove_media: [],
});

interface Props {
    index: number;
    item: ItemForm;
    open: boolean;
    onToggle: () => void;
    /** Functional update so async image compression never writes stale state. */
    onUpdate: (update: (item: ItemForm) => ItemForm) => void;
    onRemove?: () => void;
    /** Errors for this item only, keys relative to it (e.g. "item_name", "new_photos.0"). */
    errors: Record<string, string>;
    categories: Option[];
    dimensionUnits: Option[];
    woodTypes: string[];
    polishTypes: string[];
}

const firstError = (errors: Record<string, string>, prefix: string) =>
    Object.entries(errors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1];

export default function OrderItemCard({
    index,
    item,
    open,
    onToggle,
    onUpdate,
    onRemove,
    errors,
    categories,
    dimensionUnits,
    woodTypes,
    polishTypes,
}: Props) {
    const [moreOpen, setMoreOpen] = useState(Boolean(item.description || item.design_no || item.remarks));
    const patch = (values: Partial<ItemForm>) => onUpdate((current) => ({ ...current, ...values }));
    const hasErrors = Object.keys(errors).length > 0;

    const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);
    const keptPhotos = item.photos.filter((photo) => !item.remove_media.includes(photo.id));
    const keptDesigns = item.designs.filter((design) => !item.remove_media.includes(design.id));
    // Memoised: a fresh slice every render would mint and revoke a URL each time.
    const coverFiles = useMemo(() => item.new_photos.slice(0, 1), [item.new_photos]);
    const [firstNewPreview] = useObjectUrls(coverFiles);
    const cover = keptPhotos[0]?.thumb ?? firstNewPreview;

    const stepQuantity = (delta: number) => patch({ quantity: String(Math.max(1, Number(item.quantity || 0) + delta)) });

    const pickCategory = (value: string) => {
        const label = categories.find((c) => String(c.value) === value)?.label ?? '';
        patch(item.item_name === '' && label ? { category_id: value, item_name: label } : { category_id: value });
    };

    return (
        <div className={cn('rounded-lg border', hasErrors && 'border-destructive')}>
            <button type="button" onClick={onToggle} className="flex w-full items-center gap-3 p-3 text-left">
                <div className="bg-muted h-12 w-12 shrink-0 overflow-hidden rounded-md">
                    {cover && <img src={cover} alt="" className="h-full w-full object-cover" />}
                </div>
                <div className="min-w-0 flex-1">
                    <p className="truncate font-medium">
                        {toBengaliDigits(index + 1)}. {item.item_name || 'নতুন পণ্য'}
                    </p>
                    <p className="text-muted-foreground text-sm">
                        {toBengaliDigits(item.quantity || '0')} × ৳ {toBengaliDigits(item.unit_price || '0')}
                        {' · '}ছবি {toBengaliDigits(keptPhotos.length + item.new_photos.length)}
                        {' · '}নকশা {toBengaliDigits(keptDesigns.length + item.new_designs.length)}
                    </p>
                </div>
                <Money amount={lineTotal.toFixed(2)} className="font-medium" />
                <ChevronDown className={cn('h-4 w-4 shrink-0 transition-transform', open && 'rotate-180')} />
            </button>

            {open && (
                <div className="grid gap-4 border-t p-4 sm:grid-cols-2">
                    <SelectField
                        id={`category_${item.client_key}`}
                        label="ক্যাটাগরি"
                        value={item.category_id}
                        onChange={pickCategory}
                        options={categories}
                        emptyLabel="নির্ধারিত নয়"
                        error={errors.category_id}
                    />
                    <TextField
                        id={`item_name_${item.client_key}`}
                        label="পণ্যের নাম"
                        value={item.item_name}
                        onChange={(value) => patch({ item_name: value })}
                        error={errors.item_name}
                        required
                        placeholder="যেমন: সেগুন কাঠের খাট"
                    />

                    <Field id={`quantity_${item.client_key}`} label="পরিমাণ" required error={errors.quantity}>
                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" className="h-12 w-12" onClick={() => stepQuantity(-1)} aria-label="কমান">
                                <Minus className="h-4 w-4" />
                            </Button>
                            <Input
                                id={`quantity_${item.client_key}`}
                                inputMode="decimal"
                                value={item.quantity}
                                onChange={(e) => patch({ quantity: e.target.value })}
                                className="h-12 text-center text-base"
                            />
                            <Button type="button" variant="outline" className="h-12 w-12" onClick={() => stepQuantity(1)} aria-label="বাড়ান">
                                <Plus className="h-4 w-4" />
                            </Button>
                        </div>
                    </Field>
                    <TextField
                        id={`unit_price_${item.client_key}`}
                        label="দর"
                        type="number"
                        numeric
                        value={item.unit_price}
                        onChange={(value) => patch({ unit_price: value })}
                        error={errors.unit_price}
                        required
                    />

                    <div className="sm:col-span-2">
                        <ChoiceChips
                            id={`wood_${item.client_key}`}
                            label="কাঠ"
                            value={item.wood_type}
                            onChange={(value) => patch({ wood_type: value })}
                            options={woodTypes}
                            error={errors.wood_type}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <ChoiceChips
                            id={`polish_${item.client_key}`}
                            label="পলিশ"
                            value={item.polish_type}
                            onChange={(value) => patch({ polish_type: value })}
                            options={polishTypes}
                            error={errors.polish_type}
                        />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <div className="grid grid-cols-3 gap-2">
                            {(['length', 'width', 'height'] as const).map((dimension) => (
                                <TextField
                                    key={dimension}
                                    id={`${dimension}_${item.client_key}`}
                                    label={{ length: 'লম্বা', width: 'চওড়া', height: 'উচ্চতা' }[dimension]}
                                    type="number"
                                    numeric
                                    value={item[dimension]}
                                    onChange={(value) => patch({ [dimension]: value })}
                                    error={errors[dimension]}
                                />
                            ))}
                        </div>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={item.dimension_unit}
                            onValueChange={(value) => value && patch({ dimension_unit: value })}
                            className="justify-start"
                        >
                            {dimensionUnits.map((unit) => (
                                <ToggleGroupItem key={unit.value} value={String(unit.value)} className="h-11 px-4">
                                    {unit.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </div>

                    <TextField
                        id={`target_date_${item.client_key}`}
                        label="কাজ শেষের তারিখ"
                        type="date"
                        value={item.target_date}
                        onChange={(value) => patch({ target_date: value })}
                        error={errors.target_date}
                    />

                    <div className="sm:col-span-2">
                        <ImagePicker
                            label="পণ্যের ছবি"
                            existing={item.photos}
                            removedIds={item.remove_media}
                            newFiles={item.new_photos}
                            onAdd={(files) => onUpdate((current) => ({ ...current, new_photos: [...current.new_photos, ...files] }))}
                            onRemoveNew={(i) => onUpdate((current) => ({ ...current, new_photos: current.new_photos.filter((_, n) => n !== i) }))}
                            onRemoveExisting={(id) => onUpdate((current) => ({ ...current, remove_media: [...current.remove_media, id] }))}
                            onRestore={() =>
                                onUpdate((current) => ({
                                    ...current,
                                    remove_media: current.remove_media.filter((id) => !current.photos.some((p) => p.id === id)),
                                }))
                            }
                            error={firstError(errors, 'new_photos') ?? firstError(errors, 'remove_media')}
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <ImagePicker
                            label="নকশা / ডিজাইনের ছবি"
                            existing={item.designs}
                            removedIds={item.remove_media}
                            newFiles={item.new_designs}
                            onAdd={(files) => onUpdate((current) => ({ ...current, new_designs: [...current.new_designs, ...files] }))}
                            onRemoveNew={(i) => onUpdate((current) => ({ ...current, new_designs: current.new_designs.filter((_, n) => n !== i) }))}
                            onRemoveExisting={(id) => onUpdate((current) => ({ ...current, remove_media: [...current.remove_media, id] }))}
                            onRestore={() =>
                                onUpdate((current) => ({
                                    ...current,
                                    remove_media: current.remove_media.filter((id) => !current.designs.some((d) => d.id === id)),
                                }))
                            }
                            error={firstError(errors, 'new_designs')}
                        />
                    </div>

                    <div className="sm:col-span-2">
                        <button
                            type="button"
                            onClick={() => setMoreOpen(!moreOpen)}
                            className="text-muted-foreground flex items-center gap-1 text-sm"
                        >
                            <ChevronDown className={cn('h-4 w-4 transition-transform', moreOpen && 'rotate-180')} />
                            আরও তথ্য (বিবরণ, ডিজাইন নং, মন্তব্য)
                        </button>
                    </div>
                    {moreOpen && (
                        <>
                            <TextField
                                id={`design_no_${item.client_key}`}
                                label="ডিজাইন নম্বর"
                                value={item.design_no}
                                onChange={(value) => patch({ design_no: value })}
                                error={errors.design_no}
                            />
                            <TextField
                                id={`description_${item.client_key}`}
                                label="বিবরণ"
                                value={item.description}
                                onChange={(value) => patch({ description: value })}
                                error={errors.description}
                            />
                            <div className="sm:col-span-2">
                                <TextField
                                    id={`remarks_${item.client_key}`}
                                    label="মন্তব্য"
                                    value={item.remarks}
                                    onChange={(value) => patch({ remarks: value })}
                                    error={errors.remarks}
                                />
                            </div>
                        </>
                    )}

                    {onRemove && (
                        <div className="sm:col-span-2">
                            <Button type="button" variant="outline" className="text-destructive h-11 w-full" onClick={onRemove}>
                                <Trash2 className="h-4 w-4" />
                                এই পণ্য বাদ দিন
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
