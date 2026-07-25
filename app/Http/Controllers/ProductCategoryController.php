<?php

namespace App\Http\Controllers;

use App\Actions\ProductCategories\DeleteProductCategory;
use App\Actions\ProductCategories\SaveProductCategory;
use App\Http\Requests\MasterData\ProductCategoryRequest;
use App\Models\ProductCategory;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('product-categories/index', [
            'categories' => ProductCategory::query()
                ->with('parent:id,name')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('product_categories.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('product-categories/create', [
            'parents' => $this->parentOptions(),
        ]);
    }

    public function store(ProductCategoryRequest $request, SaveProductCategory $save): RedirectResponse
    {
        $save->handle($request->validated());

        return to_route('product-categories.index')->with('success', 'ক্যাটাগরি যোগ করা হয়েছে।');
    }

    public function edit(ProductCategory $productCategory): Response
    {
        return Inertia::render('product-categories/edit', [
            'category' => $productCategory,
            'parents' => $this->parentOptions($productCategory),
        ]);
    }

    public function update(ProductCategoryRequest $request, ProductCategory $productCategory, SaveProductCategory $save): RedirectResponse
    {
        $save->handle($request->validated(), $productCategory);

        return to_route('product-categories.index')->with('success', 'তথ্য বদলানো হয়েছে।');
    }

    public function destroy(ProductCategory $productCategory, DeleteProductCategory $delete): RedirectResponse
    {
        try {
            $delete->handle($productCategory);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'ক্যাটাগরি মুছে ফেলা হয়েছে।');
    }

    /**
     * Only top level categories may be a parent, which keeps the tree two deep
     * and makes a cycle unreachable from the form.
     *
     * @return list<array{value: int, label: string}>
     */
    private function parentOptions(?ProductCategory $exclude = null): array
    {
        return ProductCategory::query()
            ->whereNull('parent_id')
            ->when($exclude, fn ($query) => $query->whereKeyNot($exclude->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductCategory $category) => ['value' => $category->id, 'label' => $category->name])
            ->all();
    }
}
