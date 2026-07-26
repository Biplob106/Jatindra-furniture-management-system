<?php

namespace App\Queries;

use App\Models\Purchase;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * What we owe suppliers, and how long it has been owed.
 *
 * Age is counted from the purchase date rather than the agreed due date, which
 * is what docs/schema.md section 10 asks for. The two answer different
 * questions: the due date says whether we are late, the purchase date says how
 * long the money has been sitting with us. A supplier deciding whether to keep
 * extending credit is asking the second one.
 *
 * Buckets are half-open — 0-30, 31-60, 61-90, then everything older — so every
 * challan lands in exactly one and the buckets add up to the total.
 */
class SupplierPayableAging
{
    public const BUCKETS = [
        ['key' => 'current', 'from' => 0, 'to' => 30],
        ['key' => 'days31', 'from' => 31, 'to' => 60],
        ['key' => 'days61', 'from' => 61, 'to' => 90],
        ['key' => 'days90plus', 'from' => 91, 'to' => null],
    ];

    /**
     * Totals per bucket across every supplier, plus the grand total.
     *
     * @return array{current: string, days31: string, days61: string, days90plus: string, total: string}
     */
    public function totals(?string $asOf = null): array
    {
        $asOf = $asOf ?? CarbonImmutable::today()->toDateString();

        $row = Purchase::query()
            ->owing()
            ->selectRaw($this->bucketSelect($asOf).', COALESCE(SUM(due_amount), 0) AS total')
            ->first();

        return [
            'current' => $this->money($row?->current),
            'days31' => $this->money($row?->days31),
            'days61' => $this->money($row?->days61),
            'days90plus' => $this->money($row?->days90plus),
            'total' => $this->money($row?->total),
        ];
    }

    /**
     * One row per supplier who is owed something, worst first.
     *
     * @return list<array<string, mixed>>
     */
    public function bySupplier(?string $asOf = null): array
    {
        $asOf = $asOf ?? CarbonImmutable::today()->toDateString();

        return Purchase::query()
            ->owing()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->groupBy('purchases.supplier_id', 'suppliers.name', 'suppliers.business_name', 'suppliers.phone')
            ->selectRaw('
                purchases.supplier_id,
                suppliers.name,
                suppliers.business_name,
                suppliers.phone,
                COALESCE(SUM(purchases.due_amount), 0) AS due_total,
                COUNT(*) AS challan_count,
                MIN(purchases.purchase_date) AS oldest_date,
                '.$this->bucketSelect($asOf, 'purchases.')
            )
            ->orderByDesc('due_total')
            ->get()
            ->map(fn ($row) => [
                'supplier_id' => (int) $row->supplier_id,
                'name' => $row->name,
                'business_name' => $row->business_name,
                'phone' => $row->phone,
                'due_total' => $this->money($row->due_total),
                'challan_count' => (int) $row->challan_count,
                'oldest_date' => $row->oldest_date,
                'oldest_age' => (int) CarbonImmutable::parse($row->oldest_date)
                    ->diffInDays(CarbonImmutable::parse($asOf)),
                'current' => $this->money($row->current),
                'days31' => $this->money($row->days31),
                'days61' => $this->money($row->days61),
                'days90plus' => $this->money($row->days90plus),
            ])
            ->all();
    }

    /**
     * The bucket sums, as SQL.
     *
     * The date reaches a raw select, so nothing but a plain ISO date may get
     * this far. Same rule CashService applies to an amount it interpolates.
     */
    private function bucketSelect(string $asOf, string $prefix = ''): string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $asOf)) {
            throw new InvalidArgumentException("Refusing to age against a non-date: {$asOf}");
        }

        $age = "DATEDIFF('{$asOf}', {$prefix}purchase_date)";

        return collect(self::BUCKETS)
            ->map(function (array $bucket) use ($age, $prefix) {
                $condition = $bucket['to'] === null
                    ? "{$age} >= {$bucket['from']}"
                    : "{$age} BETWEEN {$bucket['from']} AND {$bucket['to']}";

                return "COALESCE(SUM(CASE WHEN {$condition} THEN {$prefix}due_amount ELSE 0 END), 0) AS {$bucket['key']}";
            })
            ->implode(', ');
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
