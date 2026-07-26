import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { materialUnitLabels, type MaterialUnit } from '@/types/enums';
import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler } from 'react';

interface SupplierOption {
    id: number;
    name: string;
    business_name: string | null;
    default_credit_days: number;
}

interface MaterialOption {
    id: number;
    name: string;
    unit: MaterialUnit;
    avg_cost: string;
}

interface AccountOption extends Option {
    type: string;
    balance: string;
}

interface LineForm {
    [key: string]: string;
    item_id: string;
    quantity: string;
    unit: string;
    unit_price: string;
    note: string;
}

interface PurchaseForm {
    [key: string]: string | LineForm[];
    supplier_id: string;
    shop_id: string;
    purchase_date: string;
    reference_no: string;
    payment_type: string;
    payment_due_date: string;
    transport_cost: string;
    discount: string;
    account_id: string;
    payment_method: string;
    paid_amount: string;
    note: string;
    items: LineForm[];
}

interface Props {
    suppliers: SupplierOption[];
    materials: MaterialOption[];
    shops: Option[];
    accounts: AccountOption[];
    paymentTypes: Option[];
    paymentMethods: Option[];
    defaultShopId: number | null;
    today: string;
}

const emptyLine = (): LineForm => ({ item_id: '', quantity: '', unit: '', unit_price: '', note: '' });

export default function CreatePurchase({
    suppliers,
    materials,
    shops,
    accounts,
    paymentTypes,
    paymentMethods,
    defaultShopId,
    today,
}: Props) {
    const { data, setData, post, processing, errors } = useForm<PurchaseForm>({
        supplier_id: '',
        shop_id: defaultShopId ? String(defaultShopId) : shops.length === 1 ? String(shops[0].value) : '',
        purchase_date: today,
        reference_no: '',
        payment_type: 'cash',
        payment_due_date: '',
        transport_cost: '0',
        discount: '0',
        account_id: accounts.length === 1 ? String(accounts[0].value) : '',
        payment_method: 'cash',
        paid_amount: '',
        note: '',
        items: [emptyLine()],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'কেনাকাটা', href: '/purchases' },
        { title: 'নতুন চালান', href: '#' },
    ];

    const onCredit = data.payment_type === 'credit';
    const isPartial = data.payment_type === 'partial';
    /** A credit challan is the one case where no account is needed. */
    const movesMoney = !onCredit;
    /** Anything not settled in full needs a date it falls due. */
    const leavesDue = onCredit || isPartial;

    const setLine = (index: number, patch: Record<string, string>) => {
        setData(
            'items',
            data.items.map((line, i) => (i === index ? { ...line, ...patch } : line)),
        );
    };

    /**
     * Picking a material fills its unit and its last known cost, so the common
     * case is two taps and a quantity.
     */
    const pickMaterial = (index: number, materialId: string) => {
        const material = materials.find((m) => String(m.id) === materialId);
        const lastKnownCost = material && Number(material.avg_cost) > 0 ? material.avg_cost : '';

        setLine(index, {
            item_id: materialId,
            unit: material?.unit ?? '',
            unit_price: data.items[index].unit_price || lastKnownCost,
        });
    };

    const addLine = () => setData('items', [...data.items, emptyLine()]);

    const removeLine = (index: number) =>
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );

    /** Preview only. RecordPurchase computes what is actually owed. */
    const subtotal = data.items.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0);
    const total = subtotal + Number(data.transport_cost || 0) - Number(data.discount || 0);
    const paid = onCredit ? 0 : isPartial ? Number(data.paid_amount || 0) : total;
    const due = Math.max(total - paid, 0);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('purchases.store'));
    };

    const supplier = suppliers.find((s) => String(s.id) === data.supplier_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="নতুন চালান" />

            <form onSubmit={submit} className="flex max-w-3xl flex-col gap-6 p-4 pb-28">
                <div>
                    <h1 className="text-2xl font-semibold">নতুন চালান</h1>
                    <p className="text-muted-foreground text-sm">কার কাছ থেকে কী এসেছে, আর টাকা দেওয়া হয়েছে কি না</p>
                </div>

                <FlashMessages />

                <SelectField
                    id="supplier_id"
                    label="সরবরাহকারী"
                    value={data.supplier_id}
                    onChange={(v) => setData('supplier_id', v)}
                    options={suppliers.map((s) => ({
                        value: String(s.id),
                        label: s.business_name ? `${s.name} — ${s.business_name}` : s.name,
                    }))}
                    error={errors.supplier_id}
                    required
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        id="purchase_date"
                        label="কেনার তারিখ"
                        type="date"
                        value={data.purchase_date}
                        onChange={(v) => setData('purchase_date', v)}
                        error={errors.purchase_date}
                        required
                    />

                    <TextField
                        id="reference_no"
                        label="চালান নম্বর"
                        value={data.reference_no}
                        onChange={(v) => setData('reference_no', v)}
                        error={errors.reference_no}
                        hint="সরবরাহকারীর কাগজে যে নম্বর আছে"
                    />
                </div>

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

                <div className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <Label className="text-base">মালামাল</Label>
                        <Button type="button" variant="outline" size="sm" onClick={addLine} className="h-10">
                            <Plus className="h-4 w-4" />
                            আরেকটি
                        </Button>
                    </div>

                    {errors.items && <p className="text-destructive text-sm">{errors.items}</p>}

                    {data.items.map((line, index) => {
                        const material = materials.find((m) => String(m.id) === line.item_id);
                        const lineTotal = Number(line.quantity || 0) * Number(line.unit_price || 0);

                        return (
                            <div key={index} className="flex flex-col gap-3 rounded-lg border p-3">
                                <div className="flex items-start gap-2">
                                    <div className="flex-1">
                                        <SelectField
                                            id={`items.${index}.item_id`}
                                            label="কী কেনা হয়েছে"
                                            value={line.item_id}
                                            onChange={(v) => pickMaterial(index, v)}
                                            options={materials.map((m) => ({ value: String(m.id), label: m.name }))}
                                            error={errors[`items.${index}.item_id` as keyof typeof errors] as string | undefined}
                                            required
                                        />
                                    </div>

                                    {data.items.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="mt-7 h-11 w-11 shrink-0"
                                            title="বাদ দিন"
                                            onClick={() => removeLine(index)}
                                        >
                                            <Trash2 className="text-destructive h-4 w-4" />
                                        </Button>
                                    )}
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`quantity-${index}`}>
                                            পরিমাণ {material && <span className="text-muted-foreground">({materialUnitLabels[material.unit]})</span>}
                                        </Label>
                                        <Input
                                            id={`quantity-${index}`}
                                            type="number"
                                            inputMode="decimal"
                                            step="0.001"
                                            className="h-11 text-base"
                                            value={line.quantity}
                                            onChange={(e) => setLine(index, { quantity: e.target.value })}
                                        />
                                        {errors[`items.${index}.quantity` as keyof typeof errors] && (
                                            <p className="text-destructive text-sm">
                                                {errors[`items.${index}.quantity` as keyof typeof errors] as string}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`unit_price-${index}`}>একক দর</Label>
                                        <Input
                                            id={`unit_price-${index}`}
                                            type="number"
                                            inputMode="decimal"
                                            step="0.01"
                                            className="h-11 text-base"
                                            value={line.unit_price}
                                            onChange={(e) => setLine(index, { unit_price: e.target.value })}
                                        />
                                        {errors[`items.${index}.unit_price` as keyof typeof errors] && (
                                            <p className="text-destructive text-sm">
                                                {errors[`items.${index}.unit_price` as keyof typeof errors] as string}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                {lineTotal > 0 && (
                                    <p className="text-muted-foreground text-right text-sm">
                                        ৳ {toBengaliDigits(lineTotal.toFixed(2))}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <TextField
                        id="transport_cost"
                        label="পরিবহন খরচ"
                        type="number"
                        numeric
                        value={data.transport_cost}
                        onChange={(v) => setData('transport_cost', v)}
                        error={errors.transport_cost}
                    />

                    <TextField
                        id="discount"
                        label="ছাড়"
                        type="number"
                        numeric
                        value={data.discount}
                        onChange={(v) => setData('discount', v)}
                        error={errors.discount}
                    />
                </div>

                <div className="flex flex-col gap-3">
                    <Label className="text-base">টাকা দেওয়া হয়েছে?</Label>
                    <div className="grid grid-cols-3 gap-2">
                        {paymentTypes.map((type) => (
                            <Button
                                key={type.value}
                                type="button"
                                variant={data.payment_type === type.value ? 'default' : 'outline'}
                                className="h-12 text-base"
                                onClick={() => setData('payment_type', String(type.value))}
                            >
                                {type.label}
                            </Button>
                        ))}
                    </div>
                    {errors.payment_type && <p className="text-destructive text-sm">{errors.payment_type}</p>}
                </div>

                {isPartial && (
                    <TextField
                        id="paid_amount"
                        label="কত টাকা দেওয়া হয়েছে"
                        type="number"
                        numeric
                        value={data.paid_amount}
                        onChange={(v) => setData('paid_amount', v)}
                        error={errors.paid_amount}
                        required
                    />
                )}

                {movesMoney && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField
                            id="account_id"
                            label="কোন হিসাব থেকে"
                            value={data.account_id}
                            onChange={(v) => setData('account_id', v)}
                            options={accounts.map((account) => ({
                                value: String(account.value),
                                label: `${account.label} — ৳ ${toBengaliDigits(account.balance)}`,
                            }))}
                            error={errors.account_id}
                            required
                        />

                        <SelectField
                            id="payment_method"
                            label="কীভাবে"
                            value={data.payment_method}
                            onChange={(v) => setData('payment_method', v)}
                            options={paymentMethods}
                            error={errors.payment_method}
                        />
                    </div>
                )}

                {leavesDue && (
                    <TextField
                        id="payment_due_date"
                        label="কবে শোধ করার কথা"
                        type="date"
                        value={data.payment_due_date}
                        onChange={(v) => setData('payment_due_date', v)}
                        error={errors.payment_due_date}
                        hint={
                            supplier && supplier.default_credit_days > 0
                                ? `খালি রাখলে ${toBengaliDigits(String(supplier.default_credit_days))} দিন ধরা হবে`
                                : undefined
                        }
                    />
                )}

                <TextField id="note" label="নোট" value={data.note} onChange={(v) => setData('note', v)} error={errors.note} />

                {/* Sticky, because the total is what the person at the counter checks against the paper slip before saving. */}
                <div className="bg-background fixed inset-x-0 bottom-0 border-t p-3 sm:pl-[var(--sidebar-width)]">
                    <div className="mx-auto flex max-w-3xl items-center gap-3">
                        <div className="flex flex-1 flex-col text-sm">
                            <span className="text-muted-foreground">
                                মোট ৳ {toBengaliDigits(total.toFixed(2))}
                            </span>
                            <span className={cn('font-semibold', due > 0 ? 'text-destructive' : 'text-emerald-600')}>
                                {due > 0 ? `বাকি ৳ ${toBengaliDigits(due.toFixed(2))}` : 'পুরো শোধ'}
                            </span>
                        </div>

                        <Button type="button" variant="outline" asChild className="h-11">
                            <Link href={route('purchases.index')}>বাতিল</Link>
                        </Button>

                        <Button type="submit" disabled={processing} className="h-11 text-base">
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            সংরক্ষণ
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
