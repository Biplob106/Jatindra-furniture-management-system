import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Money } from '@/components/money';
import { Badge } from '@/components/ui/badge';
import type { Product, ProductCategory } from '@/types/models';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

type ProductRow = Product & { category?: ProductCategory };

interface Props {
    products: Paginated<ProductRow>;
    search: string;
    low: boolean;
    lowCount: number;
    stockValue: string;
    canManage: boolean;
}

/** At or below the reorder line, and a line was actually set. */
function isLow(product: ProductRow): boolean {
    return Number(product.min_stock) > 0 && Number(product.current_stock) <= Number(product.min_stock);
}

export default function ProductsIndex({ products, search, low, lowCount, stockValue, canManage }: Props) {
    const columns: Column<ProductRow>[] = [
        {
            header: 'পণ্য',
            cell: (product) => (
                <div className="flex flex-col">
                    <span className="font-medium">{product.name}</span>
                    <span className="text-muted-foreground text-sm">
                        {toBengaliDigits(product.sku)}
                        {product.size_label && ` — ${product.size_label}`}
                    </span>
                </div>
            ),
        },
        { header: 'ক্যাটাগরি', cell: (product) => product.category?.name ?? '—', hideOnMobile: true },
        {
            header: 'দোকানে আছে',
            cell: (product) => (
                <span className={isLow(product) ? 'text-destructive font-medium' : 'font-medium'}>
                    {toBengaliDigits(product.current_stock)} টি
                </span>
            ),
        },
        { header: 'খরচ দর', cell: (product) => <Money amount={product.cost_price} />, hideOnMobile: true },
        { header: 'বিক্রয় দর', cell: (product) => <Money amount={product.sale_price} /> },
        {
            header: '',
            cell: (product) =>
                isLow(product) ? (
                    <Badge variant="destructive" className="gap-1">
                        <TriangleAlert className="size-3" />
                        মজুদ কম
                    </Badge>
                ) : null,
        },
    ];

    return (
        <MasterDataPage
            title="পণ্য"
            subtitle="দোকানে যা তৈরি অবস্থায় আছে"
            resource="products"
            rows={products}
            columns={columns}
            search={search}
            searchPlaceholder="নাম, কোড বা কাঠের ধরন"
            emptyMessage="এখনো কোনো পণ্য যোগ করা হয়নি"
            addLabel="নতুন পণ্য"
            canManage={canManage}
            rowKey={(product) => product.id}
            deleteConfirm={(product) => `"${product.name}" মুছে ফেলতে চান?`}
            banner={
                <div className="flex flex-col gap-2">
                    <div className="rounded-lg border p-4">
                        {/* What the floor is worth at cost: the figure that says
                            whether too much money is standing still. */}
                        <p className="text-muted-foreground text-sm">দোকানে থাকা পণ্যের মূল্য (খরচ দরে)</p>
                        <p className="text-2xl font-semibold">
                            <Money amount={stockValue} />
                        </p>
                    </div>

                    {lowCount > 0 && (
                        <Link
                            href={low ? route('products.index') : route('products.index', { low: 1 })}
                            className="border-destructive/40 bg-destructive/5 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium"
                        >
                            <TriangleAlert className="size-4 shrink-0" />
                            {low ? 'সব পণ্য দেখুন' : `${toBengaliDigits(String(lowCount))} টি পণ্যের মজুদ কমে গেছে — দেখুন`}
                        </Link>
                    )}
                </div>
            }
        />
    );
}
