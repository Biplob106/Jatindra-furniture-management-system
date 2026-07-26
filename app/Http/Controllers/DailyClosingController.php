<?php

namespace App\Http\Controllers;

use App\Actions\Cash\CloseDay;
use App\Models\DailyClosing;
use App\Models\Shop;
use App\Queries\DailyCashFigures;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DailyClosingController extends Controller
{
    public function __construct(private readonly DailyCashFigures $figures) {}

    /**
     * Tonight's closing: what the books say against what is in the drawer.
     */
    public function index(Request $request): Response
    {
        $date = $this->resolveDate($request);
        $shop = $this->resolveShop($request);

        $figures = $shop ? $this->figures->forShopOn($shop->id, $date) : null;

        $existing = $shop
            ? DailyClosing::with('closedBy:id,name')
                ->where('shop_id', $shop->id)
                ->where('closing_date', $date)
                ->first()
            : null;

        return Inertia::render('daily-closing/index', [
            'date' => $date,
            'shopId' => $shop?->id,
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $s) => ['value' => $s->id, 'label' => $s->name])
                ->all(),
            'figures' => $figures,
            'existing' => $existing ? [
                'counted_cash' => $existing->counted_cash,
                'difference' => $existing->difference,
                'note' => $existing->note,
                'closed_by' => $existing->closedBy?->name,
                'closed_at' => $existing->closed_at?->toDateTimeString(),
            ] : null,
            'recent' => $shop ? $this->recentFor($shop) : [],
            'canClose' => $request->user()->can('daily_closing.run'),
        ]);
    }

    public function store(Request $request, CloseDay $closeDay): RedirectResponse
    {
        abort_unless($request->user()->can('daily_closing.run'), 403);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'closing_date' => ['required', 'date', 'before_or_equal:today'],
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:99999999999999', 'decimal:0,2'],
            'note' => ['nullable', 'string'],
        ], [
            'counted_cash.required' => 'গোনা টাকার পরিমাণ দিন।',
            'closing_date.before_or_equal' => 'ভবিষ্যতের দিন বন্ধ করা যাবে না।',
        ]);

        $closeDay->handle(
            shop: Shop::findOrFail($validated['shop_id']),
            date: $validated['closing_date'],
            countedCash: number_format((float) $validated['counted_cash'], 2, '.', ''),
            note: $validated['note'] ?? null,
            userId: $request->user()->id,
        );

        return to_route('daily-closing.index', [
            'date' => $validated['closing_date'],
            'shop_id' => $validated['shop_id'],
        ])->with('success', 'দিনের হিসাব বন্ধ করা হয়েছে।');
    }

    /**
     * The last fortnight, so a run of short drawers is visible rather than
     * being one bad night nobody connects to the last one.
     *
     * @return list<array<string, mixed>>
     */
    private function recentFor(Shop $shop): array
    {
        return DailyClosing::query()
            ->where('shop_id', $shop->id)
            ->orderByDesc('closing_date')
            ->limit(14)
            ->get()
            ->map(fn (DailyClosing $closing) => [
                'closing_date' => $closing->closing_date->toDateString(),
                'expected_closing' => $closing->expected_closing,
                'counted_cash' => $closing->counted_cash,
                'difference' => $closing->difference,
            ])
            ->all();
    }

    private function resolveDate(Request $request): string
    {
        $today = CarbonImmutable::today();

        $date = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString()),
            $today,
            report: false
        );

        return $date->greaterThan($today) ? $today->toDateString() : $date->toDateString();
    }

    private function resolveShop(Request $request): ?Shop
    {
        $requested = $request->integer('shop_id');

        if ($requested) {
            return Shop::find($requested);
        }

        // A single-shop business should never have to pick.
        return $request->user()->shop
            ?? Shop::where('is_active', true)->orderBy('name')->first();
    }
}
