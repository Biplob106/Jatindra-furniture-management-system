import { Column, toBengaliDigits } from '@/components/data-table';
import { MasterDataPage } from '@/components/master-data-page';
import { Badge } from '@/components/ui/badge';
import { materialCategoryLabels, materialUnitLabels } from '@/types/enums';
import type { Material } from '@/types/models';
import type { Paginated } from '@/types/pagination';
import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

interface Props {
    materials: Paginated<Material>;
    search: string;
    low: boolean;
    lowCount: number;
    canManage: boolean;
}

/** At or below the reorder line, and a line was actually set. */
function isLow(material: Material): boolean {
    return Number(material.min_stock) > 0 && Number(material.current_stock) <= Number(material.min_stock);
}

export default function MaterialsIndex({ materials, search, low, lowCount, canManage }: Props) {
    const columns: Column<Material>[] = [
        {
            header: 'নাম',
            cell: (material) => (
                <div className="flex flex-col">
                    <span className="font-medium">{material.name}</span>
                    <span className="text-muted-foreground text-sm">{materialCategoryLabels[material.category]}</span>
                </div>
            ),
        },
        {
            header: 'মজুদ',
            cell: (material) => (
                <span className={isLow(material) ? 'text-destructive font-medium' : 'font-medium'}>
                    {toBengaliDigits(material.current_stock)} {materialUnitLabels[material.unit]}
                </span>
            ),
        },
        {
            header: 'গড় দর',
            cell: (material) => (Number(material.avg_cost) > 0 ? `৳ ${toBengaliDigits(material.avg_cost)}` : '—'),
            hideOnMobile: true,
        },
        {
            header: 'সর্বনিম্ন',
            cell: (material) => (Number(material.min_stock) > 0 ? toBengaliDigits(material.min_stock) : '—'),
            hideOnMobile: true,
        },
        {
            header: '',
            cell: (material) =>
                isLow(material) ? (
                    <Badge variant="destructive" className="gap-1">
                        <TriangleAlert className="size-3" />
                        মজুদ কম
                    </Badge>
                ) : null,
        },
    ];

    return (
        <MasterDataPage
            title="মালামাল"
            subtitle="গুদামে কী আছে আর কত আছে"
            resource="materials"
            rows={materials}
            columns={columns}
            search={search}
            searchPlaceholder="মালামালের নাম"
            emptyMessage="এখনো কোনো মালামাল যোগ করা হয়নি"
            addLabel="নতুন মালামাল"
            canManage={canManage}
            rowKey={(material) => material.id}
            deleteConfirm={(material) => `"${material.name}" মুছে ফেলতে চান?`}
            banner={
                lowCount > 0 && (
                    <Link
                        href={low ? route('materials.index') : route('materials.index', { low: 1 })}
                        className="border-destructive/40 bg-destructive/5 text-destructive flex items-center gap-2 rounded-lg border px-4 py-3 text-sm font-medium"
                    >
                        <TriangleAlert className="size-4 shrink-0" />
                        {low ? 'সব মালামাল দেখুন' : `${toBengaliDigits(String(lowCount))} টি মালামালের মজুদ কমে গেছে — দেখুন`}
                    </Link>
                )
            }
        />
    );
}
