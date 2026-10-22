<?php

namespace App\Http\Controllers;

use App\Actions\DeductionCategories\DeleteDeductionCategory;
use App\Actions\DeductionCategories\SaveDeductionCategory;
use App\Http\Requests\MasterData\DeductionCategoryRequest;
use App\Models\DeductionCategory;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeductionCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('deduction-categories/index', [
            'categories' => DeductionCategory::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('deduction_categories.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('deduction-categories/create');
    }

    public function store(DeductionCategoryRequest $request, SaveDeductionCategory $save): RedirectResponse
    {
        $save->handle($request->validated());

        return to_route('deduction-categories.index')->with('success', 'কর্তনের খাত যোগ করা হয়েছে।');
    }

    public function edit(DeductionCategory $deductionCategory): Response
    {
        return Inertia::render('deduction-categories/edit', ['category' => $deductionCategory]);
    }

    public function update(DeductionCategoryRequest $request, DeductionCategory $deductionCategory, SaveDeductionCategory $save): RedirectResponse
    {
        $save->handle($request->validated(), $deductionCategory);

        return to_route('deduction-categories.index')->with('success', 'তথ্য বদলানো হয়েছে।');
    }

    public function destroy(DeductionCategory $deductionCategory, DeleteDeductionCategory $delete): RedirectResponse
    {
        try {
            $delete->handle($deductionCategory);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কর্তনের খাত মুছে ফেলা হয়েছে।');
    }
}
