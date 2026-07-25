<?php

namespace App\Http\Controllers;

use App\Actions\Trades\DeleteTrade;
use App\Actions\Trades\SaveTrade;
use App\Http\Requests\MasterData\TradeRequest;
use App\Models\Trade;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TradeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('trades/index', [
            'trades' => Trade::query()
                ->withCount('employees')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('trades.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('trades/create');
    }

    public function store(TradeRequest $request, SaveTrade $saveTrade): RedirectResponse
    {
        $saveTrade->handle($request->validated());

        return to_route('trades.index')->with('success', 'কাজের ধরন যোগ করা হয়েছে।');
    }

    public function edit(Trade $trade): Response
    {
        return Inertia::render('trades/edit', ['trade' => $trade]);
    }

    public function update(TradeRequest $request, Trade $trade, SaveTrade $saveTrade): RedirectResponse
    {
        $saveTrade->handle($request->validated(), $trade);

        return to_route('trades.index')->with('success', 'তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Trade $trade, DeleteTrade $deleteTrade): RedirectResponse
    {
        try {
            $deleteTrade->handle($trade);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কাজের ধরন মুছে ফেলা হয়েছে।');
    }
}
