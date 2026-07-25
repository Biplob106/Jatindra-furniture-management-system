<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\RecordExpense;
use App\Enums\PaymentMethod;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Models\Account;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Shop running costs.
 *
 * There is no edit or delete. An expense has a transactions row behind it, and
 * changing one without the other desyncs the cash box from the books. A wrong
 * expense is corrected with an adjustment, the same rule the ledgers follow.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $this->resolveMonth($request);
        $categoryId = $request->integer('category_id') ?: null;

        $expenses = Expense::query()
            ->with(['category:id,name', 'account:id,name', 'shop:id,name'])
            ->whereBetween('expense_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Expense $expense) => [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date->toDateString(),
                'category' => $expense->category->name,
                'amount' => $expense->amount,
                'paid_to' => $expense->paid_to,
                'payment_method' => $expense->payment_method->value,
                'account' => $expense->account?->name,
                'shop' => $expense->shop?->name,
                'note' => $expense->note,
            ]);

        return Inertia::render('expenses/index', [
            'expenses' => $expenses,
            'month' => $month->format('Y-m'),
            'categoryId' => $categoryId,
            'monthTotal' => $this->monthTotal($month, $categoryId),
            'byCategory' => $this->byCategory($month),
            'categories' => $this->categoryOptions(),
            'canRecord' => $request->user()->can('expenses.record'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('expenses/create', [
            'categories' => $this->categoryOptions(),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'current_balance'])
                ->map(fn (Account $account) => [
                    'value' => $account->id,
                    'label' => $account->name,
                    'balance' => $account->current_balance,
                ])
                ->all(),
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
            'paymentMethods' => array_map(
                fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                PaymentMethod::cases()
            ),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(ExpenseRequest $request, RecordExpense $recordExpense): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $recordExpense->handle(
                data: [
                    ...$validated,
                    'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                ],
                account: Account::findOrFail($validated['account_id']),
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // The drawer could not cover it. Nothing was written.
            return back()->with('error', $e->getMessage());
        }

        return to_route('expenses.index')->with('success', 'খরচ লেখা হয়েছে।');
    }

    private function monthTotal(CarbonImmutable $month, ?int $categoryId): string
    {
        $total = Expense::query()
            ->whereBetween('expense_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Where the month's money went, biggest first. This is the question the
     * owner actually asks.
     *
     * @return list<array{name: string, total: string}>
     */
    private function byCategory(CarbonImmutable $month): array
    {
        return Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.category_id')
            ->whereBetween('expense_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->orderByDesc('total')
            ->selectRaw('expense_categories.name, SUM(expenses.amount) AS total')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'total' => number_format((float) $row->total, 2, '.', ''),
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function categoryOptions(): array
    {
        return ExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ExpenseCategory $category) => ['value' => $category->id, 'label' => $category->name])
            ->all();
    }

    private function resolveMonth(Request $request): CarbonImmutable
    {
        return rescue(
            fn () => CarbonImmutable::createFromFormat('Y-m-d', $request->string('month')->toString().'-01'),
            CarbonImmutable::today(),
            report: false
        );
    }
}
