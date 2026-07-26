import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { materialMovementTypeLabels, materialUnitLabels, type MaterialMovementType, type MaterialUnit } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface MovementRow {
    id: number;
    movement_date: string;
    type: MaterialMovementType;
    quantity: string;
    unit_cost: string;
    note: string | null;
    material: { id: number; name: string; unit: MaterialUnit };
    order: { id: number; order_no: string | null } | null;
}

interface MaterialOption {
    id: number;
    name: string;
    unit: MaterialUnit;
    current_stock: string;
}

interface IssueForm {
    [key: string]: string;
    material_id: string;
    quantity: string;
    movement_date: string;
    type: string;
    order_id: string;
    note: string;
}

interface CountForm {
    [key: string]: string;
    material_id: string;
    counted_stock: string;
    movement_date: string;
    note: string;
}

interface Props {
    movements: Paginated<MovementRow>;
    materialId: number | null;
    type: string;
    materials: MaterialOption[];
    orders: Option[];
    types: Option[];
    issueTypes: Option[];
    today: string;
    canAdjust: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'গুদাম', href: '/stock' },
];

/** Only `in` and `return` add to the floor; the rest take from it. */
const addsStock = (type: MaterialMovementType) => type === 'in' || type === 'return';

export default function StockIndex({ movements, materialId, type, materials, orders, types, issueTypes, today, canAdjust }: Props) {
    const [panel, setPanel] = useState<'issue' | 'count' | null>(null);

    const issueForm = useForm<IssueForm>({
        material_id: '',
        quantity: '',
        movement_date: today,
        type: 'out',
        order_id: '',
        note: '',
    });

    const countForm = useForm<CountForm>({
        material_id: '',
        counted_stock: '',
        movement_date: today,
        note: '',
    });

    const materialOptions = materials.map((material) => ({
        value: String(material.id),
        label: `${material.name} — ${toBengaliDigits(material.current_stock)} ${materialUnitLabels[material.unit]}`,
    }));

    const submitIssue: FormEventHandler = (e) => {
        e.preventDefault();
        issueForm.post(route('stock.issue'), {
            preserveScroll: true,
            onSuccess: () => {
                issueForm.reset('quantity', 'note');
                setPanel(null);
            },
        });
    };

    const submitCount: FormEventHandler = (e) => {
        e.preventDefault();
        countForm.post(route('stock.count'), {
            preserveScroll: true,
            onSuccess: () => {
                countForm.reset('counted_stock', 'note');
                setPanel(null);
            },
        });
    };

    const filter = (patch: Record<string, string | number | undefined>) => {
        router.get(
            route('stock.index'),
            { material_id: materialId ?? undefined, type: type || undefined, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    /** The material the count form is about, so its books can be shown beside the count. */
    const counting = materials.find((material) => String(material.id) === countForm.data.material_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="গুদাম" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">গুদাম</h1>
                    <p className="text-muted-foreground text-sm">কী বেরিয়েছে, কোন কাজে, আর কী নষ্ট হয়েছে</p>
                </div>

                <FlashMessages />

                {canAdjust && panel === null && (
                    <div className="grid grid-cols-2 gap-2">
                        <Button className="h-12 text-base" onClick={() => setPanel('issue')}>
                            মালামাল দিন
                        </Button>
                        <Button variant="outline" className="h-12 text-base" onClick={() => setPanel('count')}>
                            গুদাম গণনা
                        </Button>
                    </div>
                )}

                {canAdjust && panel === 'issue' && (
                    <form onSubmit={submitIssue} className="flex flex-col gap-4 rounded-lg border p-4">
                        <p className="font-medium">মালামাল দিন</p>

                        <SelectField
                            id="material_id"
                            label="কোন মালামাল"
                            value={issueForm.data.material_id}
                            onChange={(v) => issueForm.setData('material_id', v)}
                            options={materialOptions}
                            error={issueForm.errors.material_id}
                            required
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id="quantity"
                                label="পরিমাণ"
                                type="number"
                                numeric
                                value={issueForm.data.quantity}
                                onChange={(v) => issueForm.setData('quantity', v)}
                                error={issueForm.errors.quantity}
                                required
                            />

                            <SelectField
                                id="type"
                                label="কেন"
                                value={issueForm.data.type}
                                onChange={(v) => issueForm.setData('type', v)}
                                options={issueTypes}
                                error={issueForm.errors.type}
                                required
                            />
                        </div>

                        <SelectField
                            id="order_id"
                            label="কোন কাজে"
                            value={issueForm.data.order_id}
                            onChange={(v) => issueForm.setData('order_id', v)}
                            options={orders}
                            error={issueForm.errors.order_id}
                            hint="সাধারণ কাজে হলে খালি রাখুন। অর্ডার বাছলে ঐ কাজের খরচে যোগ হবে।"
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id="movement_date"
                                label="তারিখ"
                                type="date"
                                value={issueForm.data.movement_date}
                                onChange={(v) => issueForm.setData('movement_date', v)}
                                error={issueForm.errors.movement_date}
                                required
                            />

                            <TextField
                                id="note"
                                label="নোট"
                                value={issueForm.data.note}
                                onChange={(v) => issueForm.setData('note', v)}
                                error={issueForm.errors.note}
                            />
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={issueForm.processing} className="h-11 flex-1 text-base">
                                {issueForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                সংরক্ষণ
                            </Button>
                            <Button type="button" variant="outline" className="h-11" onClick={() => setPanel(null)}>
                                বাতিল
                            </Button>
                        </div>
                    </form>
                )}

                {canAdjust && panel === 'count' && (
                    <form onSubmit={submitCount} className="flex flex-col gap-4 rounded-lg border p-4">
                        <p className="font-medium">গুদাম গণনা</p>
                        <p className="text-muted-foreground text-sm">
                            গুনে যত পাওয়া গেছে তত লিখুন। খাতার সাথে মিলে গেলে কিছু লেখা হবে না।
                        </p>

                        <SelectField
                            id="count_material_id"
                            label="কোন মালামাল"
                            value={countForm.data.material_id}
                            onChange={(v) => countForm.setData('material_id', v)}
                            options={materialOptions}
                            error={countForm.errors.material_id}
                            required
                        />

                        <TextField
                            id="counted_stock"
                            label="গুনে যত পাওয়া গেছে"
                            type="number"
                            numeric
                            value={countForm.data.counted_stock}
                            onChange={(v) => countForm.setData('counted_stock', v)}
                            error={countForm.errors.counted_stock}
                            required
                            hint={
                                counting
                                    ? `খাতায় আছে ${toBengaliDigits(counting.current_stock)} ${materialUnitLabels[counting.unit]}`
                                    : undefined
                            }
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <TextField
                                id="count_movement_date"
                                label="তারিখ"
                                type="date"
                                value={countForm.data.movement_date}
                                onChange={(v) => countForm.setData('movement_date', v)}
                                error={countForm.errors.movement_date}
                                required
                            />

                            <TextField
                                id="count_note"
                                label="কারণ"
                                value={countForm.data.note}
                                onChange={(v) => countForm.setData('note', v)}
                                error={countForm.errors.note}
                            />
                        </div>

                        <div className="flex gap-2">
                            <Button type="submit" disabled={countForm.processing} className="h-11 flex-1 text-base">
                                {countForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                সংরক্ষণ
                            </Button>
                            <Button type="button" variant="outline" className="h-11" onClick={() => setPanel(null)}>
                                বাতিল
                            </Button>
                        </div>
                    </form>
                )}

                <div className="flex flex-wrap gap-2">
                    <Button variant={type === '' ? 'default' : 'outline'} size="sm" onClick={() => filter({ type: undefined })}>
                        সব
                    </Button>
                    {types.map((option) => (
                        <Button
                            key={option.value}
                            variant={type === String(option.value) ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => filter({ type: String(option.value) })}
                        >
                            {option.label}
                        </Button>
                    ))}
                </div>

                {movements.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        এখনো কোনো হিসাব লেখা হয়নি
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {movements.data.map((movement) => (
                            <div key={movement.id} className="flex items-center gap-3 rounded-lg border p-3">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-medium">{movement.material.name}</p>
                                    <p className="text-muted-foreground truncate text-sm">
                                        {toBengaliDigits(movement.movement_date)}
                                        {movement.order && ` — ${toBengaliDigits(movement.order.order_no ?? '')}`}
                                        {movement.note && ` — ${movement.note}`}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className={addsStock(movement.type) ? 'font-semibold text-emerald-600' : 'font-semibold'}>
                                        {addsStock(movement.type) ? '+' : '−'} {toBengaliDigits(movement.quantity)}{' '}
                                        {materialUnitLabels[movement.material.unit]}
                                    </p>
                                    <Badge variant="outline">{materialMovementTypeLabels[movement.type]}</Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {movements.links.length > 3 && (
                    <div className="flex flex-wrap gap-1">
                        {movements.links.map((link, index) => (
                            <Button
                                key={index}
                                variant={link.active ? 'default' : 'outline'}
                                size="sm"
                                disabled={link.url === null}
                                asChild={link.url !== null}
                            >
                                {link.url !== null ? (
                                    <Link href={link.url} preserveScroll>
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    </Link>
                                ) : (
                                    <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                )}
                            </Button>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
