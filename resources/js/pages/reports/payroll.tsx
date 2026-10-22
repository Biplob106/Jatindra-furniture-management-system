import { Option } from '@/components/form-field';
import { Money } from '@/components/money';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { wageTypeLabels, type WageType } from '@/types/enums';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download } from 'lucide-react';

interface PayrollRow {
    id: number;
    name: string;
    employee_code: string;
    wage_type: WageType;
    earned: string;
    night: string;
    paid: string;
    deductions: string;
    balance: string;
}

interface Props {
    rows: PayrollRow[];
    totals: { earned: string; night: string; paid: string; deductions: string; outstanding: string };
    from: string;
    to: string;
    shopId: number | null;
    shops: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'পে-রোল রিপোর্ট', href: '/reports/payroll' },
];

const ALL = '__all__';

export default function PayrollReport({ rows, totals, from, to, shopId, shops }: Props) {
    const reload = (changes: Record<string, string>) => {
        const next: Record<string, string | number> = {
            from,
            to,
            ...(shopId ? { shop_id: shopId } : {}),
            ...changes,
        };

        for (const key of Object.keys(next)) {
            if (next[key] === '') delete next[key];
        }

        router.get(route('reports.payroll'), next, { preserveState: true, preserveScroll: true, replace: true });
    };

    const csvHref = route('reports.payroll', {
        from,
        to,
        ...(shopId ? { shop_id: shopId } : {}),
        export: 'csv',
    });

    const cards = [
        { label: 'মোট জমা', amount: totals.earned },
        { label: 'রাত্রি ভাতা', amount: totals.night },
        { label: 'পরিশোধ', amount: totals.paid },
        { label: 'কর্তন', amount: totals.deductions },
        { label: 'মোট বকেয়া', amount: totals.outstanding },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="পে-রোল রিপোর্ট" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">পে-রোল রিপোর্ট</h1>
                        <p className="text-muted-foreground text-sm">কর্মীভিত্তিক জমা, পরিশোধ, কর্তন ও বকেয়া</p>
                    </div>
                    <Button variant="outline" asChild>
                        <a href={csvHref}>
                            <Download className="h-4 w-4" />
                            CSV ডাউনলোড
                        </a>
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="from">শুরু</Label>
                        <Input id="from" type="date" value={from} max={to} onChange={(e) => reload({ from: e.target.value })} className="h-12" />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="to">শেষ</Label>
                        <Input id="to" type="date" value={to} min={from} onChange={(e) => reload({ to: e.target.value })} className="h-12" />
                    </div>
                    {shops.length > 0 && (
                        <div className="grid gap-2">
                            <Label htmlFor="shop_id">দোকান</Label>
                            <Select
                                value={shopId ? String(shopId) : ALL}
                                onValueChange={(value) => reload({ shop_id: value === ALL ? '' : value })}
                            >
                                <SelectTrigger id="shop_id" className="h-12">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL}>সব দোকান</SelectItem>
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

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    {cards.map((card) => (
                        <div key={card.label} className="rounded-lg border p-4">
                            <p className="text-muted-foreground text-sm">{card.label}</p>
                            <p className="text-xl font-semibold">
                                <Money amount={card.amount} />
                            </p>
                        </div>
                    ))}
                </div>

                {rows.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        এই সময়ের মধ্যে কোনো হিসাব নেই
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full text-sm">
                            <thead className="bg-muted/50 text-muted-foreground">
                                <tr>
                                    <th className="p-3 text-left font-medium">কর্মী</th>
                                    <th className="p-3 text-right font-medium">জমা</th>
                                    <th className="p-3 text-right font-medium">রাত</th>
                                    <th className="p-3 text-right font-medium">পরিশোধ</th>
                                    <th className="p-3 text-right font-medium">কর্তন</th>
                                    <th className="p-3 text-right font-medium">বকেয়া</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="p-3">
                                            <p className="font-medium">{row.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {row.employee_code} · {wageTypeLabels[row.wage_type]}
                                            </p>
                                        </td>
                                        <td className="p-3 text-right">
                                            <Money amount={row.earned} />
                                        </td>
                                        <td className="p-3 text-right">
                                            <Money amount={row.night} />
                                        </td>
                                        <td className="p-3 text-right">
                                            <Money amount={row.paid} />
                                        </td>
                                        <td className="p-3 text-right">
                                            <Money amount={row.deductions} />
                                        </td>
                                        <td className="p-3 text-right">
                                            <Money amount={row.balance} signed />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
