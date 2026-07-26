<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\RecordPurchase;
use App\Enums\CashPaymentMethod;
use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Http\Requests\Purchases\PurchaseRequest;
use App\Models\Account;
use App\Models\Material;
use App\Models\Purchase;
use App\Models\Shop;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseController extends Controller
{
    /**
     * The challan book. Unpaid first when the filter asks, because the reason
     * anyone opens this screen is usually to find out what is still owed.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $purchases = Purchase::query()
            ->with('supplier:id,name,business_name')
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('purchase_no', 'like', "%{$search}%")
                ->orWhere('reference_no', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn ($s) => $s
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                )
            ))
            ->when($status === 'owing', fn ($query) => $query->owing())
            ->when($status !== '' && $status !== 'owing', fn ($query) => $query->where('status', $status))
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'purchase_no' => $purchase->purchase_no,
                'reference_no' => $purchase->reference_no,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'payment_due_date' => $purchase->payment_due_date?->toDateString(),
                'payment_type' => $purchase->payment_type->value,
                'status' => $purchase->status->value,
                'total_amount' => $purchase->total_amount,
                'due_amount' => $purchase->due_amount,
                'supplier' => [
                    'id' => $purchase->supplier->id,
                    'name' => $purchase->supplier->name,
                    'business_name' => $purchase->supplier->business_name,
                ],
            ]);

        return Inertia::render('purchases/index', [
            'purchases' => $purchases,
            'search' => $search,
            'status' => $status,
            'statuses' => $this->statusOptions(),
            'today' => CarbonImmutable::today()->toDateString(),
            'canRecord' => $request->user()->can('purchases.record'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('purchases/create', [
            'suppliers' => Supplier::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'business_name', 'default_credit_days'])
                ->map(fn (Supplier $supplier) => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'business_name' => $supplier->business_name,
                    'default_credit_days' => $supplier->default_credit_days,
                ])
                ->all(),
            'materials' => Material::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'avg_cost'])
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit->value,
                    'avg_cost' => $material->avg_cost,
                ])
                ->all(),
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
            'accounts' => Account::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'type', 'current_balance'])
                ->map(fn (Account $account) => [
                    'value' => $account->id,
                    'label' => $account->name,
                    'type' => $account->type->value,
                    'balance' => $account->current_balance,
                ])
                ->all(),
            'paymentTypes' => array_map(
                fn (PurchasePaymentType $type) => ['value' => $type->value, 'label' => $type->label()],
                PurchasePaymentType::cases()
            ),
            'paymentMethods' => array_map(
                fn (CashPaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                CashPaymentMethod::cases()
            ),
            'defaultShopId' => $request->user()->shop_id,
            'today' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    public function store(PurchaseRequest $request, RecordPurchase $record): RedirectResponse
    {
        $validated = $request->validated();

        $account = isset($validated['account_id'])
            ? Account::findOrFail($validated['account_id'])
            : null;

        try {
            $purchase = $record->handle(
                data: $validated,
                items: $validated['items'],
                supplier: Supplier::findOrFail($validated['supplier_id']),
                account: $account,
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // A cash box that cannot cover the payment. The whole challan is
            // refused rather than leaving stock that was never paid for.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return to_route('purchases.index')->with(
            'success',
            "চালান {$purchase->purchase_no} সংরক্ষণ করা হয়েছে।"
        );
    }

    /**
     * "Owing" first, because the reason anyone filters this list is usually to
     * find out what is still owed rather than to read a status back.
     *
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'owing', 'label' => 'বাকি আছে'],
            ...array_map(
                fn (PurchaseStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                PurchaseStatus::cases()
            ),
        ];
    }
}
