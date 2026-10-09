<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Supplier;
use App\Queries\SpendingReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchases and expenses for a month on one page. Owner only: it is the
 * shop's whole cost side.
 */
class SpendingReportController extends Controller
{
    public function __invoke(Request $request, SpendingReport $report): Response
    {
        $month = rescue(
            fn () => CarbonImmutable::createFromFormat('!Y-m', $request->string('month')->toString()),
            CarbonImmutable::today(),
            report: false
        );

        $from = $month->startOfMonth()->toDateString();
        $to = $month->endOfMonth()->toDateString();
        $shopId = $request->integer('shop_id') ?: null;
        $supplierId = $request->integer('supplier_id') ?: null;
        $search = $request->string('search')->trim()->toString();

        return Inertia::render('reports/spending', [
            ...$report->summary($from, $to, $shopId, $supplierId),
            'rows' => $report->rows($from, $to, $shopId, $supplierId, $search)
                ->paginate(50)
                ->withQueryString(),
            'month' => $month->format('Y-m'),
            'shopId' => $shopId,
            'supplierId' => $supplierId,
            'search' => $search,
            'shops' => Shop::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
            'suppliers' => Supplier::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Supplier $supplier) => ['value' => $supplier->id, 'label' => $supplier->name])
                ->all(),
        ]);
    }
}
