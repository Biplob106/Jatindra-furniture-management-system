import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, LoaderCircle, Plus, Search, Trash2, UserPlus } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface CustomerOption {
    id: number;
    name: string;
    phone: string;
    area: string | null;
}

interface ItemForm {
    [key: string]: string | number | null | undefined;
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
}

interface OrderForm {
    [key: string]: string | number | null | ItemForm[];
    customer_id: string;
    shop_id: string;
    order_date: string;
    expected_delivery_date: string;
    discount: string;
    delivery_charge: string;
    delivery_address: string;
    note: string;
    items: ItemForm[];
}

interface Props {
    order?: {
        id: number;
        order_no: string | null;
        customer_id: number;
        shop_id: number;
        order_date: string;
        expected_delivery_date: string | null;
        discount: string;
        delivery_charge: string;
        delivery_address: string | null;
        note: string | null;
        items: (Partial<ItemForm> & { id: number })[];
    };
    customers: CustomerOption[];
    customerSearch: string;
    shops: Option[];
    categories: Option[];
    dimensionUnits: Option[];
    today: string;
}

const emptyItem = (): ItemForm => ({
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
});

export default function CreateOrder({ order, customers, customerSearch, shops, categories, dimensionUnits, today }: Props) {
    const isEdit = Boolean(order);

    const { data, setData, post, put, processing, errors } = useForm<OrderForm>({
        customer_id: order ? String(order.customer_id) : '',
        shop_id: order ? String(order.shop_id) : shops.length === 1 ? String(shops[0].value) : '',
        order_date: order?.order_date ?? today,
        expected_delivery_date: order?.expected_delivery_date ?? '',
        discount: order?.discount ?? '0',
        delivery_charge: order?.delivery_charge ?? '0',
        delivery_address: order?.delivery_address ?? '',
        note: order?.note ?? '',
        items: order
            ? order.items.map((item) => ({ ...emptyItem(), ...item }) as ItemForm)
            : [emptyItem()],
    });

    const [phoneTerm, setPhoneTerm] = useState(customerSearch);
    const [openItem, setOpenItem] = useState<number | null>(0);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'ড্যাশবোর্ড', href: '/dashboard' },
        { title: 'অর্ডার', href: '/orders' },
        { title: isEdit ? (order!.order_no ?? 'খসড়া') : 'নতুন', href: '#' },
    ];

    /**
     * Customers are filtered on the server through a partial reload, so a shop
     * with thousands of them does not ship the lot to the browser.
     */
    const searchCustomers = () => {
        router.get(
            isEdit ? route('orders.edit', order!.id) : route('orders.create'),
            { customer_search: phoneTerm },
            { only: ['customers', 'customerSearch'], preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const setItem = (index: number, patch: Partial<ItemForm>) => {
        setData(
            'items',
            data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)),
        );
    };

    const addItem = () => {
        setData('items', [...data.items, emptyItem()]);
        setOpenItem(data.items.length);
    };

    const removeItem = (index: number) => {
        setData('items', data.items.filter((_, i) => i !== index));
        setOpenItem(null);
    };

    /** Preview only. The server computes what is actually charged. */
    const subtotal = data.items.reduce(
        (sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0),
        0,
    );
    const total = subtotal - Number(data.discount || 0) + Number(data.delivery_charge || 0);

    const selectedCustomer = customers.find((c) => String(c.id) === data.customer_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (isEdit) {
            put(route('orders.update', order!.id));
        } else {
            post(route('orders.store'));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEdit ? 'অর্ডার বদলান' : 'নতুন অর্ডার'} />

            <form onSubmit={submit} className="flex flex-col gap-5 p-4 pb-28">
                <div>
                    <h1 className="text-2xl font-semibold">{isEdit ? 'অর্ডার বদলান' : 'নতুন অর্ডার'}</h1>
                    <p className="text-muted-foreground text-sm">
                        {isEdit ? (order!.order_no ?? 'খসড়া অর্ডার') : 'খসড়া হিসেবে সংরক্ষণ হবে, নিশ্চিত করলে নম্বর পাবে'}
                    </p>
                </div>

                <FlashMessages />

                {/* Customer */}
                <section className="flex flex-col gap-3 rounded-lg border p-4">
                    <h2 className="font-medium">কাস্টমার</h2>

                    {selectedCustomer ? (
                        <div className="flex items-center justify-between gap-3 rounded-lg border p-3">
                            <div className="min-w-0">
                                <p className="truncate font-medium">{selectedCustomer.name}</p>
                                <p className="text-muted-foreground text-sm">
                                    {toBengaliDigits(selectedCustomer.phone)}
                                    {selectedCustomer.area && ` · ${selectedCustomer.area}`}
                                </p>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={() => setData('customer_id', '')}>
                                বদলান
                            </Button>
                        </div>
                    ) : (
                        <>
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2" />
                                    <Input
                                        type="search"
                                        inputMode="numeric"
                                        value={phoneTerm}
                                        onChange={(e) => setPhoneTerm(e.target.value)}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                searchCustomers();
                                            }
                                        }}
                                        placeholder="মোবাইল নম্বর বা নাম"
                                        className="h-12 pr-9 text-base"
                                    />
                                </div>
                                <Button type="button" variant="outline" className="h-12" onClick={searchCustomers}>
                                    খুঁজুন
                                </Button>
                            </div>

                            <div className="flex flex-col gap-2">
                                {customers.map((customer) => (
                                    <button
                                        key={customer.id}
                                        type="button"
                                        onClick={() => setData('customer_id', String(customer.id))}
                                        className="hover:bg-muted/50 rounded-lg border p-3 text-left transition-colors"
                                    >
                                        <p className="font-medium">{customer.name}</p>
                                        <p className="text-muted-foreground text-sm">
                                            {toBengaliDigits(customer.phone)}
                                            {customer.area && ` · ${customer.area}`}
                                        </p>
                                    </button>
                                ))}

                                {customers.length === 0 && (
                                    <p className="text-muted-foreground py-2 text-sm">
                                        কোনো কাস্টমার পাওয়া যায়নি।
                                    </p>
                                )}

                                <Button type="button" variant="outline" className="h-11" asChild>
                                    <Link href={route('customers.create')}>
                                        <UserPlus className="h-4 w-4" />
                                        নতুন কাস্টমার যোগ করুন
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}

                    {errors.customer_id && <p className="text-destructive text-sm">{errors.customer_id}</p>}
                </section>

                {/* Order details */}
                <section className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2">
                    <SelectField
                        id="shop_id"
                        label="দোকান"
                        value={data.shop_id}
                        onChange={(value) => setData('shop_id', value)}
                        options={shops}
                        error={errors.shop_id}
                        required
                    />
                    <TextField
                        id="order_date"
                        label="অর্ডারের তারিখ"
                        type="date"
                        value={data.order_date}
                        onChange={(value) => setData('order_date', value)}
                        error={errors.order_date}
                        required
                    />
                    <TextField
                        id="expected_delivery_date"
                        label="ডেলিভারির তারিখ"
                        type="date"
                        value={data.expected_delivery_date}
                        onChange={(value) => setData('expected_delivery_date', value)}
                        error={errors.expected_delivery_date}
                    />
                    <TextField
                        id="delivery_address"
                        label="ডেলিভারির ঠিকানা"
                        value={data.delivery_address}
                        onChange={(value) => setData('delivery_address', value)}
                        error={errors.delivery_address}
                    />
                </section>

                {/* Items */}
                <section className="flex flex-col gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">আইটেম</h2>
                        <span className="text-muted-foreground text-sm">{toBengaliDigits(data.items.length)} টি</span>
                    </div>

                    {errors.items && <p className="text-destructive text-sm">{errors.items}</p>}

                    {data.items.map((item, index) => {
                        const isOpen = openItem === index;
                        const lineTotal = Number(item.quantity || 0) * Number(item.unit_price || 0);

                        return (
                            <div key={index} className="rounded-lg border">
                                <button
                                    type="button"
                                    onClick={() => setOpenItem(isOpen ? null : index)}
                                    className="flex w-full items-center gap-3 p-4 text-left"
                                >
                                    <ChevronDown className={cn('h-4 w-4 shrink-0 transition-transform', isOpen && 'rotate-180')} />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">
                                            {item.item_name || `আইটেম ${toBengaliDigits(index + 1)}`}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {toBengaliDigits(item.quantity || '0')} × ৳ {toBengaliDigits(item.unit_price || '0')}
                                        </p>
                                    </div>
                                    <Money amount={lineTotal.toFixed(2)} className="font-medium" />
                                </button>

                                {isOpen && (
                                    <div className="grid gap-4 border-t p-4 sm:grid-cols-2">
                                        <div className="sm:col-span-2">
                                            <TextField
                                                id={`item_name_${index}`}
                                                label="আইটেমের নাম"
                                                value={item.item_name}
                                                onChange={(value) => setItem(index, { item_name: value })}
                                                error={errors[`items.${index}.item_name` as keyof typeof errors] as string}
                                                required
                                                placeholder="যেমন: সেগুন কাঠের খাট"
                                            />
                                        </div>

                                        <TextField
                                            id={`quantity_${index}`}
                                            label="পরিমাণ"
                                            type="number"
                                            numeric
                                            value={item.quantity}
                                            onChange={(value) => setItem(index, { quantity: value })}
                                            error={errors[`items.${index}.quantity` as keyof typeof errors] as string}
                                            required
                                        />

                                        <TextField
                                            id={`unit_price_${index}`}
                                            label="দর"
                                            type="number"
                                            numeric
                                            value={item.unit_price}
                                            onChange={(value) => setItem(index, { unit_price: value })}
                                            error={errors[`items.${index}.unit_price` as keyof typeof errors] as string}
                                            required
                                        />

                                        <SelectField
                                            id={`category_${index}`}
                                            label="ক্যাটাগরি"
                                            value={item.category_id}
                                            onChange={(value) => setItem(index, { category_id: value })}
                                            options={categories}
                                            emptyLabel="নির্ধারিত নয়"
                                        />

                                        <TextField
                                            id={`wood_type_${index}`}
                                            label="কাঠ"
                                            value={item.wood_type}
                                            onChange={(value) => setItem(index, { wood_type: value })}
                                            placeholder="সেগুন, মেহগনি, চাম্বল"
                                        />

                                        <div className="grid grid-cols-3 gap-2 sm:col-span-2">
                                            <TextField
                                                id={`length_${index}`}
                                                label="লম্বা"
                                                type="number"
                                                numeric
                                                value={item.length}
                                                onChange={(value) => setItem(index, { length: value })}
                                            />
                                            <TextField
                                                id={`width_${index}`}
                                                label="চওড়া"
                                                type="number"
                                                numeric
                                                value={item.width}
                                                onChange={(value) => setItem(index, { width: value })}
                                            />
                                            <TextField
                                                id={`height_${index}`}
                                                label="উচ্চতা"
                                                type="number"
                                                numeric
                                                value={item.height}
                                                onChange={(value) => setItem(index, { height: value })}
                                            />
                                        </div>

                                        <SelectField
                                            id={`dimension_unit_${index}`}
                                            label="মাপের একক"
                                            value={item.dimension_unit}
                                            onChange={(value) => setItem(index, { dimension_unit: value })}
                                            options={dimensionUnits}
                                        />

                                        <TextField
                                            id={`polish_${index}`}
                                            label="পলিশ"
                                            value={item.polish_type}
                                            onChange={(value) => setItem(index, { polish_type: value })}
                                        />

                                        <TextField
                                            id={`design_no_${index}`}
                                            label="ডিজাইন নম্বর"
                                            value={item.design_no}
                                            onChange={(value) => setItem(index, { design_no: value })}
                                        />

                                        <TextField
                                            id={`target_date_${index}`}
                                            label="কাজ শেষের তারিখ"
                                            type="date"
                                            value={item.target_date}
                                            onChange={(value) => setItem(index, { target_date: value })}
                                        />

                                        <div className="sm:col-span-2">
                                            <TextField
                                                id={`remarks_${index}`}
                                                label="মন্তব্য"
                                                value={item.remarks}
                                                onChange={(value) => setItem(index, { remarks: value })}
                                            />
                                        </div>

                                        {data.items.length > 1 && (
                                            <div className="sm:col-span-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    className="text-destructive h-11 w-full"
                                                    onClick={() => removeItem(index)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    এই আইটেম বাদ দিন
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}

                    <Button type="button" variant="outline" className="h-12 text-base" onClick={addItem}>
                        <Plus className="h-4 w-4" />
                        আইটেম যোগ করুন
                    </Button>
                </section>

                {/* Money */}
                <section className="flex flex-col gap-4 rounded-lg border p-4">
                    <h2 className="font-medium">হিসাব</h2>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            id="discount"
                            label="ছাড়"
                            type="number"
                            numeric
                            value={data.discount}
                            onChange={(value) => setData('discount', value)}
                            error={errors.discount}
                        />
                        <TextField
                            id="delivery_charge"
                            label="ডেলিভারি খরচ"
                            type="number"
                            numeric
                            value={data.delivery_charge}
                            onChange={(value) => setData('delivery_charge', value)}
                            error={errors.delivery_charge}
                        />
                    </div>

                    <div className="flex flex-col gap-1 border-t pt-3 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">আইটেমের মোট</span>
                            <Money amount={subtotal.toFixed(2)} />
                        </div>
                        <div className="flex justify-between text-base font-semibold">
                            <span>সর্বমোট</span>
                            <Money amount={total.toFixed(2)} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="note">নোট</Label>
                        <Input
                            id="note"
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            className="h-12 text-base"
                        />
                    </div>
                </section>

                <div className="bg-background/95 fixed inset-x-0 bottom-0 border-t p-4 backdrop-blur">
                    <div className="mx-auto flex max-w-5xl items-center gap-4">
                        <div className="min-w-0 flex-1">
                            <p className="text-muted-foreground text-sm">সর্বমোট</p>
                            <p className="text-lg font-semibold">
                                <Money amount={total.toFixed(2)} />
                            </p>
                        </div>
                        <Button type="submit" className="h-12 px-8 text-base" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            সংরক্ষণ করুন
                        </Button>
                    </div>
                </div>
            </form>
        </AppLayout>
    );
}
