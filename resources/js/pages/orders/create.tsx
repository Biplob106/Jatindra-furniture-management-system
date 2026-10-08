import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, SelectField, TextField } from '@/components/form-field';
import { type OrderImage } from '@/components/image-picker';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Search, UserPlus } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';
import OrderItemCard, { emptyItem, type ItemForm } from './order-item-card';

interface CustomerOption {
    id: number;
    name: string;
    phone: string;
    area: string | null;
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
        items: (Partial<Record<keyof ItemForm, unknown>> & { id: number; category_id: number | null; photos: OrderImage[]; designs: OrderImage[] })[];
    };
    customers: CustomerOption[];
    customerSearch: string;
    shops: Option[];
    categories: Option[];
    dimensionUnits: Option[];
    woodTypes: string[];
    polishTypes: string[];
    today: string;
}

/** Server rows carry nulls; the form's inputs want empty strings. */
const stripNulls = <T extends object>(row: T) => Object.fromEntries(Object.entries(row).map(([key, value]) => [key, value ?? ''])) as T;

/** yyyy-mm-dd plus n days, in UTC so no timezone shifts the date. */
const addDays = (iso: string, days: number) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10);
};

export default function CreateOrder({ order, customers, customerSearch, shops, categories, dimensionUnits, woodTypes, polishTypes, today }: Props) {
    const isEdit = Boolean(order);

    const { data, setData, post, transform, processing, errors } = useForm<OrderForm>({
        customer_id: order ? String(order.customer_id) : '',
        shop_id: order ? String(order.shop_id) : shops.length === 1 ? String(shops[0].value) : '',
        order_date: order?.order_date ?? today,
        expected_delivery_date: order?.expected_delivery_date ?? '',
        discount: order?.discount ?? '0',
        delivery_charge: order?.delivery_charge ?? '0',
        delivery_address: order?.delivery_address ?? '',
        note: order?.note ?? '',
        items: order
            ? order.items.map(
                  (item) =>
                      ({
                          ...emptyItem(),
                          ...stripNulls(item),
                          category_id: item.category_id ? String(item.category_id) : '',
                          client_key: `item-${item.id}`,
                      }) as ItemForm,
              )
            : [emptyItem()],
    });

    const [phoneTerm, setPhoneTerm] = useState(customerSearch);
    const [openKeys, setOpenKeys] = useState<string[]>(order ? [] : [data.items[0].client_key]);

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

    const toggle = (key: string) => setOpenKeys((keys) => (keys.includes(key) ? keys.filter((k) => k !== key) : [...keys, key]));

    const updateItem = (key: string, update: (item: ItemForm) => ItemForm) =>
        setData((current) => ({ ...current, items: current.items.map((item) => (item.client_key === key ? update(item) : item)) }));

    const addItem = () => {
        const item = emptyItem();
        setData((current) => ({ ...current, items: [...current.items, item] }));
        setOpenKeys((keys) => [...keys, item.client_key]);
    };

    const removeItem = (key: string) => setData((current) => ({ ...current, items: current.items.filter((item) => item.client_key !== key) }));

    /** Errors for item `index`, with the "items.N." prefix stripped. */
    const itemErrors = (index: number): Record<string, string> =>
        Object.fromEntries(
            Object.entries(errors as Record<string, string>)
                .filter(([key]) => key.startsWith(`items.${index}.`))
                .map(([key, message]) => [key.slice(`items.${index}.`.length), message]),
        );

    // A card the server complained about opens itself.
    useEffect(() => {
        const failing = data.items.filter((_, index) => Object.keys(itemErrors(index)).length > 0).map((item) => item.client_key);
        if (failing.length > 0) setOpenKeys((keys) => Array.from(new Set([...keys, ...failing])));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);

    /** Preview only. The server computes what is actually charged. */
    const subtotal = data.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0);
    const total = subtotal - Number(data.discount || 0) + Number(data.delivery_charge || 0);

    const selectedCustomer = customers.find((c) => String(c.id) === data.customer_id);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Files only travel in multipart, and PHP only parses multipart on POST,
        // so an edit is a POST that Laravel reads as PUT. client_key and the
        // existing image lists are browser-only and never sent.
        transform((current) => ({
            ...current,
            ...(isEdit ? { _method: 'put' } : {}),
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            items: current.items.map(({ client_key, photos, designs, ...rest }) => rest),
        }));

        post(isEdit ? route('orders.update', order!.id) : route('orders.store'), {
            forceFormData: true,
            preserveScroll: true,
        });
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

                                {customers.length === 0 && <p className="text-muted-foreground py-2 text-sm">কোনো কাস্টমার পাওয়া যায়নি।</p>}

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
                    <div className="grid gap-2">
                        <TextField
                            id="expected_delivery_date"
                            label="ডেলিভারির তারিখ"
                            type="date"
                            value={data.expected_delivery_date}
                            onChange={(value) => setData('expected_delivery_date', value)}
                            error={errors.expected_delivery_date}
                        />
                        <div className="flex gap-2">
                            {[7, 15, 30].map((days) => (
                                <Button
                                    key={days}
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-10"
                                    onClick={() => setData('expected_delivery_date', addDays(data.order_date || today, days))}
                                >
                                    +{toBengaliDigits(days)} দিন
                                </Button>
                            ))}
                        </div>
                    </div>
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
                        <h2 className="font-medium">পণ্য</h2>
                        <span className="text-muted-foreground text-sm">{toBengaliDigits(data.items.length)} টি</span>
                    </div>

                    {errors.items && <p className="text-destructive text-sm">{errors.items}</p>}

                    {data.items.map((item, index) => (
                        <OrderItemCard
                            key={item.client_key}
                            index={index}
                            item={item}
                            open={openKeys.includes(item.client_key)}
                            onToggle={() => toggle(item.client_key)}
                            onUpdate={(update) => updateItem(item.client_key, update)}
                            onRemove={data.items.length > 1 ? () => removeItem(item.client_key) : undefined}
                            errors={itemErrors(index)}
                            categories={categories}
                            dimensionUnits={dimensionUnits}
                            woodTypes={woodTypes}
                            polishTypes={polishTypes}
                        />
                    ))}

                    <Button type="button" variant="outline" className="h-12 text-base" onClick={addItem}>
                        <Plus className="h-4 w-4" />
                        পণ্য যোগ করুন
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
                        <Input id="note" value={data.note} onChange={(e) => setData('note', e.target.value)} className="h-12 text-base" />
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
