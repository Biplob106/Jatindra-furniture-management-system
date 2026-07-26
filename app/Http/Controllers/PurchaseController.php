<?php

namespace App\Http\Controllers;

use App\Actions\Purchases\RecordPurchase;
use App\Enums\CashPaymentMethod;
use App\Enums\PurchasePaymentType;
use App\Enums\PurchaseStatus;
use App\Http\Requests\Purchases\PurchaseRequest;
use App\Models\Account;
use App\Models\Material;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use App\Models\PurchaseItem;
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

    /**
     * One challan in full: what arrived, what it cost, and every payment that
     * has been put against it since.
     */
    public function show(Purchase $purchase): Response
    {
        $purchase->load(['supplier:id,name,business_name,phone', 'shop:id,name', 'items', 'creator:id,name']);

        // Material names for the lines. One query rather than one per line,
        // and it stays correct when a line points at a product later.
        $materials = Material::query()
            ->whereIn('id', $purchase->items->pluck('item_id'))
            ->get(['id', 'name', 'unit'])
            ->keyBy('id');

        return Inertia::render('purchases/show', [
            'purchase' => [
                'id' => $purchase->id,
                'purchase_no' => $purchase->purchase_no,
                'reference_no' => $purchase->reference_no,
                'purchase_date' => $purchase->purchase_date->toDateString(),
                'payment_due_date' => $purchase->payment_due_date?->toDateString(),
                'payment_type' => $purchase->payment_type->value,
                'status' => $purchase->status->value,
                'subtotal' => $purchase->subtotal,
                'transport_cost' => $purchase->transport_cost,
                'discount' => $purchase->discount,
                'total_amount' => $purchase->total_amount,
                'paid_amount' => $purchase->paid_amount,
                'due_amount' => $purchase->due_amount,
                'note' => $purchase->note,
                'shop' => $purchase->shop?->name,
                'created_by' => $purchase->creator?->name,
                'supplier' => [
                    'id' => $purchase->supplier->id,
                    'name' => $purchase->supplier->name,
                    'business_name' => $purchase->supplier->business_name,
                    'phone' => $purchase->supplier->phone,
                ],
            ],
            'items' => $purchase->items
                ->map(fn (PurchaseItem $item) => [
                    'id' => $item->id,
                    'name' => $materials[$item->item_id]->name ?? '—',
                    'item_type' => $item->item_type->value,
                    'quantity' => $item->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'note' => $item->note,
                ])
                ->all(),
            'payments' => $this->paymentsFor($purchase),
        ]);
    }

    /**
     * What has been paid against this challan, newest first. A single handover
     * may have settled several challans, so the payment's own total is shown
     * beside what landed here.
     *
     * @return list<array<string, mixed>>
     */
    private function paymentsFor(Purchase $purchase): array
    {
        return PaymentAllocation::query()
            ->with('payment.account:id,name')
            ->where('allocatable_type', Purchase::class)
            ->where('allocatable_id', $purchase->id)
            ->get()
            ->sortByDesc(fn (PaymentAllocation $allocation) => [
                $allocation->payment->payment_date->toDateString(),
                $allocation->id,
            ])
            ->map(fn (PaymentAllocation $allocation) => [
                'id' => $allocation->id,
                'allocated_amount' => $allocation->allocated_amount,
                'payment_date' => $allocation->payment->payment_date->toDateString(),
                'payment_total' => $allocation->payment->amount,
                'payment_method' => $allocation->payment->payment_method->value,
                'reference_no' => $allocation->payment->reference_no,
                'account' => $allocation->payment->account?->name,
            ])
            ->values()
            ->all();
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
