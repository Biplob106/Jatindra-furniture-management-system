import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option, TextField } from '@/components/form-field';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CheckCircle2, LoaderCircle, TriangleAlert } from 'lucide-react';
import { FormEventHandler } from 'react';

interface Figures {
    opening_balance: string;
    total_in: string;
    total_out: string;
    net_amount: string;
    expected_closing: string;
    total_receivable: string;
    credit_purchase_today: string;
    total_payable: string;
}

interface Recent {
    closing_date: string;
    expected_closing: string;
    counted_cash: string;
    difference: string;
}

interface Props {
    date: string;
    shopId: number | null;
    shops: Option[];
    figures: Figures | null;
    existing: {
        counted_cash: string;
        difference: string;
        note: string | null;
        closed_by: string | null;
        closed_at: string | null;
    } | null;
    recent: Recent[];
    canClose: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'দিনের হিসাব', href: '/daily-closing' },
];

export default function DailyClosingIndex({ date, shopId, shops, figures, existing, recent, canClose }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        shop_id: shopId ? String(shopId) : '',
        closing_date: date,
        counted_cash: existing?.counted_cash ?? '',
        note: existing?.note ?? '',
    });

    const reload = (params: { date?: string; shop_id?: string }) => {
        router.get(
            route('daily-closing.index'),
            { date: params.date ?? date, shop_id: params.shop_id ?? (shopId ? String(shopId) : '') },
            { preserveState: false, preserveScroll: true },
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('daily-closing.store'), { preserveScroll: true });
    };

    /** Preview only; the server recomputes the expected figure on save. */
    const difference = figures && data.counted_cash !== '' ? Number(data.counted_cash) - Number(figures.expected_closing) : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="দিনের হিসাব" />

            <div className="flex flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">দিনের হিসাব</h1>
                    <p className="text-muted-foreground text-sm">বাক্সের টাকা গুনে মিলিয়ে নিন</p>
                </div>

                <FlashMessages />

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="date">তারিখ</Label>
                        <Input
                            id="date"
                            type="date"
                            value={date}
                            max={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => reload({ date: e.target.value })}
                            className="h-12 text-base"
                        />
                    </div>

                    {shops.length > 1 && (
                        <div className="grid gap-2">
                            <Label htmlFor="shop">দোকান</Label>
                            <Select value={shopId ? String(shopId) : ''} onValueChange={(value) => reload({ shop_id: value })}>
                                <SelectTrigger id="shop" className="h-12 text-base">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {shops.map((shop) => (
                                        <SelectItem key={shop.value} value={String(shop.value)}>
                                            {shop.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {!figures ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        কোনো দোকান পাওয়া যায়নি। আগে দোকান যোগ করুন।
                    </div>
                ) : (
                    <>
                        {existing && (
                            <div className="text-muted-foreground rounded-lg border border-dashed p-3 text-sm">
                                এই দিনের হিসাব আগেই বন্ধ করা হয়েছে
                                {existing.closed_by && ` — ${existing.closed_by}`}
                                {existing.closed_at && `, ${toBengaliDigits(existing.closed_at)}`}। আবার গুনে সংরক্ষণ করলে
                                হিসাব ঠিক হয়ে যাবে।
                            </div>
                        )}

                        <section className="flex flex-col gap-2 rounded-lg border p-4">
                            <h2 className="font-medium">খাতা যা বলছে</h2>

                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">দিনের শুরুতে ছিল</span>
                                <Money amount={figures.opening_balance} />
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">আজ জমা</span>
                                <span className="text-green-700 tabular-nums dark:text-green-400">
                                    + ৳ {toBengaliDigits(figures.total_in)}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">আজ খরচ</span>
                                <span className="text-destructive tabular-nums">− ৳ {toBengaliDigits(figures.total_out)}</span>
                            </div>

                            <div className="mt-1 flex justify-between border-t pt-2 text-base font-semibold">
                                <span>বাক্সে থাকার কথা</span>
                                <Money amount={figures.expected_closing} />
                            </div>
                        </section>

                        <form onSubmit={submit} className="flex flex-col gap-4 rounded-lg border p-4">
                            <h2 className="font-medium">গুনে দেখুন</h2>

                            <TextField
                                id="counted_cash"
                                label="বাক্সে আসলে যত টাকা আছে"
                                type="number"
                                numeric
                                value={data.counted_cash}
                                onChange={(value) => setData('counted_cash', value)}
                                error={errors.counted_cash}
                                required
                                disabled={!canClose}
                            />

                            {difference !== null && (
                                <div
                                    className={cn(
                                        'flex items-center gap-3 rounded-lg border p-4',
                                        difference === 0
                                            ? 'border-green-600/40 bg-green-600/10 text-green-700 dark:text-green-400'
                                            : 'border-destructive/40 bg-destructive/10 text-destructive',
                                    )}
                                >
                                    {difference === 0 ? (
                                        <CheckCircle2 className="h-5 w-5 shrink-0" />
                                    ) : (
                                        <TriangleAlert className="h-5 w-5 shrink-0" />
                                    )}
                                    <div>
                                        <p className="font-medium">
                                            {difference === 0
                                                ? 'হিসাব মিলে গেছে'
                                                : difference < 0
                                                  ? `৳ ${toBengaliDigits(Math.abs(difference).toFixed(2))} কম আছে`
                                                  : `৳ ${toBengaliDigits(difference.toFixed(2))} বেশি আছে`}
                                        </p>
                                        {difference !== 0 && (
                                            <p className="text-sm">কোথায় গরমিল হলো নোটে লিখে রাখুন।</p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <TextField
                                id="note"
                                label="নোট"
                                value={data.note}
                                onChange={(value) => setData('note', value)}
                                error={errors.note}
                                disabled={!canClose}
                            />

                            {canClose && (
                                <Button type="submit" className="h-12 text-base" disabled={processing}>
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    দিনের হিসাব বন্ধ করুন
                                </Button>
                            )}
                        </form>

                        <section className="grid gap-3 rounded-lg border p-4 sm:grid-cols-2">
                            <div>
                                <p className="text-muted-foreground text-sm">কাস্টমারের কাছে পাওনা</p>
                                <p className="text-lg font-semibold">
                                    <Money amount={figures.total_receivable} />
                                </p>
                            </div>
                            <div>
                                <p className="text-muted-foreground text-sm">সাপ্লায়ারকে দিতে হবে</p>
                                <p className="text-muted-foreground text-lg">এখনো হিসাব শুরু হয়নি</p>
                            </div>
                        </section>

                        {recent.length > 0 && (
                            <section className="rounded-lg border p-4">
                                <h2 className="mb-3 font-medium">গত কয়েক দিন</h2>
                                <div className="flex flex-col gap-2">
                                    {recent.map((row) => (
                                        <div key={row.closing_date} className="flex items-center justify-between gap-3 text-sm">
                                            <span className="text-muted-foreground">{toBengaliDigits(row.closing_date)}</span>
                                            <span className="flex items-center gap-3">
                                                <Money amount={row.counted_cash} />
                                                <span
                                                    className={cn(
                                                        'tabular-nums',
                                                        Number(row.difference) === 0
                                                            ? 'text-green-700 dark:text-green-400'
                                                            : 'text-destructive font-medium',
                                                    )}
                                                >
                                                    {Number(row.difference) === 0
                                                        ? 'মিল'
                                                        : `${Number(row.difference) > 0 ? '+' : '−'} ৳ ${toBengaliDigits(
                                                              Math.abs(Number(row.difference)).toFixed(2),
                                                          )}`}
                                                </span>
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
