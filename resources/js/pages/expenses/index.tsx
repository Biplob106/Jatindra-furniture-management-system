import { toBengaliDigits } from '@/components/data-table';
import { FlashMessages } from '@/components/flash-messages';
import { Option } from '@/components/form-field';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { paymentMethodLabels, type PaymentMethod } from '@/types/enums';
import type { Paginated } from '@/types/pagination';
import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface ExpenseRow {
    id: number;
    expense_date: string;
    category: string;
    amount: string;
    paid_to: string | null;
    payment_method: PaymentMethod;
    account: string | null;
    shop: string | null;
    note: string | null;
}

interface Props {
    expenses: Paginated<ExpenseRow>;
    month: string;
    categoryId: number | null;
    monthTotal: string;
    byCategory: { name: string; total: string }[];
    categories: Option[];
    canRecord: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'ড্যাশবোর্ড', href: '/dashboard' },
    { title: 'খরচ', href: '/expenses' },
];

export default function ExpensesIndex({ expenses, month, categoryId, monthTotal, byCategory, categories, canRecord }: Props) {
    const reload = (params: { month?: string; category_id?: string }) => {
        router.get(
            route('expenses.index'),
            {
                month: params.month ?? month,
                ...(params.category_id !== undefined
                    ? params.category_id
                        ? { category_id: params.category_id }
                        : {}
                    : categoryId
                      ? { category_id: categoryId }
                      : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="খরচ" />

            <div className="flex flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">খরচ</h1>
                        <p className="text-muted-foreground text-sm">দোকান ভাড়া, বিল, যাতায়াত</p>
                    </div>
                    {canRecord && (
                        <Button asChild className="h-11 text-base">
                            <Link href={route('expenses.create')}>
                                <Plus className="h-4 w-4" />
                                নতুন খরচ
                            </Link>
                        </Button>
                    )}
                </div>

                <FlashMessages />

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="month">মাস</Label>
                        <Input
                            id="month"
                            type="month"
                            value={month}
                            onChange={(e) => reload({ month: e.target.value })}
                            className="h-12 text-base"
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="category">খাত</Label>
                        <Select
                            value={categoryId ? String(categoryId) : '__all__'}
                            onValueChange={(value) => reload({ category_id: value === '__all__' ? '' : value })}
                        >
                            <SelectTrigger id="category" className="h-12 text-base">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__all__">সব খাত</SelectItem>
                                {categories.map((category) => (
                                    <SelectItem key={category.value} value={String(category.value)}>
                                        {category.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div className="rounded-lg border p-4">
                    <p className="text-muted-foreground text-sm">এই মাসের মোট খরচ</p>
                    <p className="text-2xl font-semibold">
                        <Money amount={monthTotal} />
                    </p>

                    {byCategory.length > 0 && (
                        <div className="mt-3 flex flex-col gap-1 border-t pt-3 text-sm">
                            {byCategory.map((row) => (
                                <div key={row.name} className="flex justify-between">
                                    <span className="text-muted-foreground">{row.name}</span>
                                    <Money amount={row.total} />
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {expenses.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-lg border border-dashed p-10 text-center">
                        এই মাসে কোনো খরচ লেখা হয়নি
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        {expenses.data.map((expense) => (
                            <div key={expense.id} className="flex items-start justify-between gap-3 rounded-lg border p-4">
                                <div className="min-w-0">
                                    <p className="font-medium">{expense.category}</p>
                                    <p className="text-muted-foreground text-sm">
                                        {toBengaliDigits(expense.expense_date)}
                                        {expense.paid_to && ` · ${expense.paid_to}`}
                                        {expense.account && ` · ${expense.account}`}
                                    </p>
                                    {expense.note && <p className="text-muted-foreground text-sm">{expense.note}</p>}
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <Money amount={expense.amount} className="font-semibold" />
                                    <Badge variant="outline">{paymentMethodLabels[expense.payment_method]}</Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {expenses.last_page > 1 && (
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-muted-foreground text-sm">
                            {toBengaliDigits(expenses.from ?? 0)}–{toBengaliDigits(expenses.to ?? 0)} /{' '}
                            {toBengaliDigits(expenses.total)}
                        </span>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" asChild disabled={expenses.current_page === 1}>
                                <Link
                                    href={expenses.links.find((l) => l.label === String(expenses.current_page - 1))?.url ?? '#'}
                                    preserveScroll
                                >
                                    আগের
                                </Link>
                            </Button>
                            <Button variant="outline" size="sm" asChild disabled={expenses.current_page === expenses.last_page}>
                                <Link
                                    href={expenses.links.find((l) => l.label === String(expenses.current_page + 1))?.url ?? '#'}
                                    preserveScroll
                                >
                                    পরের
                                </Link>
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
