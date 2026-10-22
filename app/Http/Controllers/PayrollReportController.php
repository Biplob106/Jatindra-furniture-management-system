<?php

namespace App\Http\Controllers;

use App\Models\PayrollSettlement;
use App\Models\Shop;
use App\Queries\PayrollSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One worker-by-worker payroll sheet for a date range: what each earned, what
 * was paid, what was deducted and what is still owed. Built entirely from
 * PayrollSummary so the page and its CSV never disagree with the ledger.
 */
class PayrollReportController extends Controller
{
    public function __invoke(Request $request, PayrollSummary $summary): Response|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $shopId = $request->integer('shop_id') ?: null;

        $rows = $summary->rows($from, $to, $shopId);

        if ($request->string('export')->toString() === 'csv') {
            return $this->csv($rows, $from, $to);
        }

        $settledIds = PayrollSettlement::query()
            ->where('period_start', $from)
            ->where('period_end', $to)
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId))
            ->whereNull('reversed_at')
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return Inertia::render('reports/payroll', [
            'rows' => $rows,
            'totals' => $summary->totals($from, $to, $shopId),
            'from' => $from,
            'to' => $to,
            'shopId' => $shopId,
            'shops' => Shop::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
            'settledIds' => $settledIds,
            'canSettle' => $request->user()->can('payroll.settle'),
        ]);
    }

    /**
     * Defaults to the current month. A malformed date falls back rather than
     * erroring, since it usually means a mistyped query string.
     *
     * @return array{string, string}
     */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::today();

        $from = rescue(
            fn () => CarbonImmutable::createFromFormat('!Y-m-d', $request->string('from')->toString()),
            $today->startOfMonth(),
            report: false
        );

        $to = rescue(
            fn () => CarbonImmutable::createFromFormat('!Y-m-d', $request->string('to')->toString()),
            $today->endOfMonth(),
            report: false
        );

        // A backwards range is pulled straight, so the query never throws.
        return $from->greaterThan($to)
            ? [$to->toDateString(), $from->toDateString()]
            : [$from->toDateString(), $to->toDateString()];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(array $rows, string $from, string $to): StreamedResponse
    {
        $filename = "payroll-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'wb');

            // BOM so Bengali names open correctly in Excel.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['কর্মী কোড', 'নাম', 'মজুরির ধরন', 'মোট জমা', 'রাত্রি ভাতা', 'পরিশোধ', 'কর্তন', 'বর্তমান পাওনা']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['employee_code'],
                    $row['name'],
                    $row['wage_type'],
                    $row['earned'],
                    $row['night'],
                    $row['paid'],
                    $row['deductions'],
                    $row['balance'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
