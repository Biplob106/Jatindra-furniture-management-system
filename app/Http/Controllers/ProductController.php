<?php

namespace App\Http\Controllers;

use App\Actions\Products\DeleteProduct;
use App\Actions\Products\SaveProduct;
use App\Http\Requests\MasterData\ProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $lowOnly = $request->boolean('low');

        return Inertia::render('products/index', [
            'products' => Product::query()
                ->with('category:id,name')
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('wood_type', 'like', "%{$search}%")
                ))
                ->when($lowOnly, fn ($query) => $query->lowStock())
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'low' => $lowOnly,
            'lowCount' => Product::query()->active()->lowStock()->count(),
            'stockValue' => $this->stockValue(),
            'canManage' => $request->user()->can('products.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create', $this->options());
    }

    public function store(ProductRequest $request, SaveProduct $save): RedirectResponse
    {
        $save->handle($request->validated(), userId: $request->user()->id);

        return to_route('products.index')->with('success', 'পণ্য যোগ করা হয়েছে।');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $product,
            ...$this->options(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $save): RedirectResponse
    {
        $save->handle($request->validated(), $product, $request->user()->id);

        return to_route('products.index')->with('success', 'পণ্যের তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Product $product, DeleteProduct $delete): RedirectResponse
    {
        try {
            $delete->handle($product);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'পণ্য মুছে ফেলা হয়েছে।');
    }

    /**
     * What the floor is worth at cost, which is the figure that matters when
     * deciding whether too much money is standing still.
     */
    private function stockValue(): string
    {
        $total = Product::query()
            ->active()
            ->selectRaw('COALESCE(SUM(current_stock * cost_price), 0) AS value')
            ->value('value');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * @return array{categories: list<array{value: int, label: string}>, shops: list<array{value: int, label: string}>}
     */
    private function options(): array
    {
        return [
            'categories' => ProductCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ProductCategory $category) => ['value' => $category->id, 'label' => $category->name])
                ->all(),
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
        ];
    }
}
