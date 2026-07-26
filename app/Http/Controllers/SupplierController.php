<?php

namespace App\Http\Controllers;

use App\Actions\Suppliers\DeleteSupplier;
use App\Actions\Suppliers\SaveSupplier;
use App\Enums\SupplierType;
use App\Http\Requests\MasterData\SupplierRequest;
use App\Models\Supplier;
use App\Services\LedgerService;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $suppliers = Supplier::query()
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
            ))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        // One query for every balance on the page rather than one per row.
        $balances = $this->ledger->supplierBalancesFor(
            $suppliers->getCollection()->pluck('id')->all()
        );

        $suppliers->setCollection(
            $suppliers->getCollection()->map(function (Supplier $supplier) use ($balances) {
                $supplier->setAttribute('balance', $balances[$supplier->id] ?? '0.00');

                return $supplier;
            })
        );

        return Inertia::render('suppliers/index', [
            'suppliers' => $suppliers,
            'search' => $search,
            'canManage' => $request->user()->can('suppliers.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('suppliers/create', ['types' => $this->typeOptions()]);
    }

    public function store(SupplierRequest $request, SaveSupplier $save): RedirectResponse
    {
        $save->handle($request->validated());

        return to_route('suppliers.index')->with('success', 'সরবরাহকারী যোগ করা হয়েছে।');
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('suppliers/edit', [
            'supplier' => $supplier,
            'types' => $this->typeOptions(),
            'balance' => $this->ledger->supplierBalanceFor($supplier),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier, SaveSupplier $save): RedirectResponse
    {
        $save->handle($request->validated(), $supplier);

        return to_route('suppliers.index')->with('success', 'সরবরাহকারীর তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Supplier $supplier, DeleteSupplier $delete): RedirectResponse
    {
        try {
            $delete->handle($supplier);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'সরবরাহকারী মুছে ফেলা হয়েছে।');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function typeOptions(): array
    {
        return array_map(
            fn (SupplierType $type) => ['value' => $type->value, 'label' => $type->label()],
            SupplierType::cases()
        );
    }
}
