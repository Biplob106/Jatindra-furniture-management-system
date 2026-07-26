<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\PaySupplier;
use App\Enums\CashPaymentMethod;
use App\Http\Requests\Purchases\SupplierPaymentRequest;
use App\Models\Account;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\SupplierLedger;
use App\Queries\SupplierPayableAging;
use App\Services\LedgerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;

class SupplierLedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly SupplierPayableAging $aging,
    ) {}

    /**
     * Who we owe, worst first, with how long it has been owed.
     */
    public function index(Request $request): Response
    {
        $asOf = CarbonImmutable::today()->toDateString();
        $search = $request->string('search')->toString();

        $rows = collect($this->aging->bySupplier($asOf))
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn (array $row) => str_contains($row['name'], $search)
                    || str_contains((string) $row['business_name'], $search)
                    || str_contains((string) $row['phone'], $search)
            ))
            ->values()
            ->all();

        return Inertia::render('supplier-ledger/index', [
            'suppliers' => $rows,
            'search' => $search,
            'totals' => $this->aging->totals($asOf),
            'asOf' => $asOf,
            'canPay' => $request->user()->can('supplier_payment.record'),
        ]);
    }

    /**
     * One supplier's book: the challans still open, every entry behind the
     * balance, and the form to hand money over.
     */
    public function show(Request $request, Supplier $supplier): Response
    {
        $asOf = CarbonImmutable::today()->toDateString();

        $entries = SupplierLedger::query()
            ->where('supplier_id', $supplier->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SupplierLedger $entry) => [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date->toDateString(),
                'type' => $entry->type->value,
                'direction' => $entry->direction->value,
                'amount' => $entry->amount,
                'note' => $entry->note,
            ]);

        return Inertia::render('supplier-ledger/show', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'business_name' => $supplier->business_name,
                'phone' => $supplier->phone,
                'supplier_type' => $supplier->supplier_type->value,
                'default_credit_days' => $supplier->default_credit_days,
                'credit_limit' => $supplier->credit_limit,
            ],
            'entries' => $entries,
            'balance' => $this->ledger->supplierBalanceFor($supplier),
            'openChallans' => $this->openChallansFor($supplier, $asOf),
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
            'paymentMethods' => array_map(
                fn (CashPaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                CashPaymentMethod::cases()
            ),
            'today' => $asOf,
            'canPay' => $request->user()->can('supplier_payment.record'),
        ]);
    }

    public function store(SupplierPaymentRequest $request, Supplier $supplier, PaySupplier $pay): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $pay->handle(
                supplier: $supplier,
                account: Account::findOrFail($validated['account_id']),
                data: $validated,
                allocations: $validated['allocations'] ?? null,
                userId: $request->user()->id,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            // A drawer that could not cover it, or an allocation that does not
            // add up. Either way nothing was written.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return to_route('supplier-ledger.show', $supplier)->with('success', 'পরিশোধ লেখা হয়েছে।');
    }

    /**
     * The challans this payment could settle, oldest first, which is the order
     * PaySupplier clears them in when nobody picks.
     *
     * @return list<array<string, mixed>>
     */
    private function openChallansFor(Supplier $supplier, string $asOf): array
    {
        return Purchase::query()
            ->where('supplier_id', $supplier->id)
            ->owing()
            ->orderBy('purchase_date')
            ->orderBy('id')
            ->get()
            ->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'purchase_no' => $purchase->purchase_no,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'payment_due_date' => $purchase->payment_due_date?->toDateString(),
                'total_amount' => $purchase->total_amount,
                'due_amount' => $purchase->due_amount,
                'age_days' => (int) $purchase->purchase_date->diffInDays(CarbonImmutable::parse($asOf)),
                'overdue' => $purchase->payment_due_date !== null
                    && $purchase->payment_due_date->toDateString() < $asOf,
            ])
            ->all();
    }
}
