<?php

namespace App\Http\Controllers;

use App\Actions\ExpenseCategories\DeleteExpenseCategory;
use App\Actions\ExpenseCategories\SaveExpenseCategory;
use App\Http\Requests\MasterData\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('expense-categories/index', [
            'categories' => ExpenseCategory::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('expense_categories.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('expense-categories/create');
    }

    public function store(ExpenseCategoryRequest $request, SaveExpenseCategory $save): RedirectResponse
    {
        $save->handle($request->validated());

        return to_route('expense-categories.index')->with('success', 'খরচের খাত যোগ করা হয়েছে।');
    }

    public function edit(ExpenseCategory $expenseCategory): Response
    {
        return Inertia::render('expense-categories/edit', ['category' => $expenseCategory]);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory, SaveExpenseCategory $save): RedirectResponse
    {
        $save->handle($request->validated(), $expenseCategory);

        return to_route('expense-categories.index')->with('success', 'তথ্য বদলানো হয়েছে।');
    }

    public function destroy(ExpenseCategory $expenseCategory, DeleteExpenseCategory $delete): RedirectResponse
    {
        try {
            $delete->handle($expenseCategory);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'খরচের খাত মুছে ফেলা হয়েছে।');
    }
}
