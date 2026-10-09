<?php

namespace App\Queries;

use App\Enums\MaterialCategory;
use App\Enums\PurchaseItemType;
use App\Enums\PurchaseStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Everything the shop spent in a period, purchases and expenses on one page.
 *
 * This is a view, not a merge. Purchases and expenses stay in their own tables
 * because they mean different things to the books: a purchase adds stock and
 * may be owed on credit, an expense is money gone. Spending is counted when it
 * was committed, so a credit purchase counts in the month the goods came in,
 * not the month it was paid. For money that actually left the drawer, the
 * cash ledger and the daily closing are the source.
 *
 * Purchases are broken down by material category from the challan lines, with
 * transport and discount as headings of their own, so the headings add up to
 * the challan totals and the grand total to the paisa. A returned challan is
 * not spending and is left out.
 *
 * Expenses have no supplier, so narrowing to a supplier shows only that
 * supplier's challans.
 */
class SpendingReport
{
    /**
     * @return array{
     *     totals: array{purchases: string, purchases_due: string, expenses: string, total: string},
     *     byHeading: list<array{kind: string, name: string, total: string}>
     * }
     */
    public function summary(string $from, string $to, ?int $shopId = null, ?int $supplierId = null): array
    {
        $this->assertDates($from, $to);

        $purchases = $this->purchases($from, $to, $shopId, $supplierId)
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total, COALESCE(SUM(due_amount), 0) AS due')
            ->first();

        $expenses = (string) $this->expenses($from, $to, $shopId, $supplierId)->sum('amount');

        $purchaseTotal = $this->money($purchases->total);
        $expenseTotal = $this->money($expenses);

        return [
            'totals' => [
                'purchases' => $purchaseTotal,
                'purchases_due' => $this->money($purchases->due),
                'expenses' => $expenseTotal,
                'total' => bcadd($purchaseTotal, $expenseTotal, 2),
            ],
            'byHeading' => $this->byHeading($from, $to, $shopId, $supplierId),
        ];
    }

    /**
     * One row per challan or expense, newest first. Returned as a query so the
     * caller can paginate it.
     */
    public function rows(string $from, string $to, ?int $shopId = null, ?int $supplierId = null, ?string $search = null): Builder
    {
        $this->assertDates($from, $to);

        $purchases = $this->purchases($from, $to, $shopId, $supplierId)
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->selectRaw("
                'purchase' AS kind,
                purchases.id,
                purchases.purchase_date AS entry_date,
                purchases.purchase_no AS reference,
                NULL AS heading,
                suppliers.name AS party,
                purchases.total_amount AS amount,
                purchases.due_amount AS due
            ");

        $expenses = $this->expenses($from, $to, $shopId, $supplierId)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->selectRaw("
                'expense' AS kind,
                expenses.id,
                expenses.expense_date AS entry_date,
                NULL AS reference,
                expense_categories.name AS heading,
                expenses.paid_to AS party,
                expenses.amount AS amount,
                CAST(0 AS DECIMAL(12,2)) AS due
            ");

        return DB::query()
            ->fromSub($purchases->unionAll($expenses), 'spending')
            ->when(filled($search), function (Builder $query) use ($search) {
                $like = '%'.$search.'%';

                $query->where(fn (Builder $q) => $q
                    ->where('party', 'like', $like)
                    ->orWhere('heading', 'like', $like)
                    ->orWhere('reference', 'like', $like));
            })
            ->orderByDesc('entry_date')
            ->orderByDesc('id');
    }

    /**
     * Where the money went, biggest first.
     *
     * @return list<array{kind: string, name: string, total: string}>
     */
    private function byHeading(string $from, string $to, ?int $shopId, ?int $supplierId): array
    {
        $lines = $this->purchases($from, $to, $shopId, $supplierId)
            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id');

        $materialLines = (clone $lines)
            ->join('materials', 'materials.id', '=', 'purchase_items.item_id')
            ->where('purchase_items.item_type', PurchaseItemType::Material->value)
            ->groupBy('materials.category')
            ->selectRaw('materials.category, SUM(purchase_items.line_total) AS total')
            ->get()
            ->map(fn ($row) => $this->heading('purchase', MaterialCategory::from($row->category)->label(), $row->total));

        $productLines = (clone $lines)
            ->where('purchase_items.item_type', PurchaseItemType::Product->value)
            ->sum('purchase_items.line_total');

        $charges = $this->purchases($from, $to, $shopId, $supplierId)
            ->selectRaw('COALESCE(SUM(transport_cost), 0) AS transport, COALESCE(SUM(discount), 0) AS discount')
            ->first();

        $expenseHeadings = $this->expenses($from, $to, $shopId, $supplierId)
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name, SUM(expenses.amount) AS total')
            ->get()
            ->map(fn ($row) => $this->heading('expense', $row->name, $row->total));

        $headings = [
            ...$materialLines,
            $this->heading('purchase', 'রেডিমেড পণ্য', $productLines),
            $this->heading('purchase', 'ক্রয়ের পরিবহন', $charges->transport),
            $this->heading('purchase', 'ক্রয়ে ছাড়', bcmul($this->money($charges->discount), '-1', 2)),
            ...$expenseHeadings,
        ];

        $headings = array_values(array_filter(
            $headings,
            fn (array $row) => bccomp($row['total'], '0.00', 2) !== 0
        ));

        usort($headings, fn (array $a, array $b) => bccomp($b['total'], $a['total'], 2));

        return $headings;
    }

    private function purchases(string $from, string $to, ?int $shopId, ?int $supplierId): Builder
    {
        return DB::table('purchases')
            ->whereBetween('purchases.purchase_date', [$from, $to])
            ->where('purchases.status', '!=', PurchaseStatus::Returned->value)
            ->when($shopId, fn (Builder $query) => $query->where('purchases.shop_id', $shopId))
            ->when($supplierId, fn (Builder $query) => $query->where('purchases.supplier_id', $supplierId));
    }

    private function expenses(string $from, string $to, ?int $shopId, ?int $supplierId): Builder
    {
        return DB::table('expenses')
            ->whereBetween('expenses.expense_date', [$from, $to])
            ->when($shopId, fn (Builder $query) => $query->where('expenses.shop_id', $shopId))
            // An expense has no supplier, so it can never match one.
            ->when($supplierId, fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    /**
     * @return array{kind: string, name: string, total: string}
     */
    private function heading(string $kind, string $name, mixed $total): array
    {
        return ['kind' => $kind, 'name' => $name, 'total' => $this->money($total)];
    }

    private function assertDates(string ...$dates): void
    {
        foreach ($dates as $date) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidArgumentException("Refusing a period bound that is not a date: {$date}");
            }
        }
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? '0'), '0', 2);
    }
}
