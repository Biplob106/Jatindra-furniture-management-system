<?php

namespace App\Http\Controllers;

use App\Actions\Shops\DeleteShop;
use App\Actions\Shops\SaveShop;
use App\Http\Requests\MasterData\ShopRequest;
use App\Models\Shop;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('shops/index', [
            'shops' => Shop::query()
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")
                ))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('shops.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('shops/create');
    }

    public function store(ShopRequest $request, SaveShop $saveShop): RedirectResponse
    {
        $saveShop->handle($request->validated());

        return to_route('shops.index')->with('success', 'দোকান যোগ করা হয়েছে।');
    }

    public function edit(Shop $shop): Response
    {
        return Inertia::render('shops/edit', ['shop' => $shop]);
    }

    public function update(ShopRequest $request, Shop $shop, SaveShop $saveShop): RedirectResponse
    {
        $saveShop->handle($request->validated(), $shop);

        return to_route('shops.index')->with('success', 'দোকানের তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Shop $shop, DeleteShop $deleteShop): RedirectResponse
    {
        try {
            $deleteShop->handle($shop);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'দোকান মুছে ফেলা হয়েছে।');
    }
}
