<?php

namespace App\Http\Controllers;

use App\Actions\Materials\AdjustStock;
use App\Actions\Materials\IssueMaterial;
use App\Enums\MaterialMovementType;
use App\Http\Requests\Materials\StockCountRequest;
use App\Http\Requests\Materials\StockIssueRequest;
use App\Models\Material;
use App\Models\MaterialMovement;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class StockController extends Controller
{
    /**
     * The store room: what has moved, and the two forms that move it.
     */
    public function index(Request $request): Response
    {
        $materialId = $request->integer('material_id');
        $type = $request->string('type')->toString();

        $movements = MaterialMovement::query()
            ->with(['material:id,name,unit', 'order:id,order_no'])
            ->when($materialId, fn ($query) => $query->where('material_id', $materialId))
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (MaterialMovement $movement) => [
                'id' => $movement->id,
                'movement_date' => $movement->movement_date->toDateString(),
                'type' => $movement->type->value,
                'quantity' => $movement->quantity,
                'unit_cost' => $movement->unit_cost,
                'note' => $movement->note,
                'material' => [
                    'id' => $movement->material->id,
                    'name' => $movement->material->name,
                    'unit' => $movement->material->unit->value,
                ],
                'order' => $movement->order ? [
                    'id' => $movement->order->id,
                    'order_no' => $movement->order->order_no,
                ] : null,
            ]);

        return Inertia::render('stock/index', [
            'movements' => $movements,
            'materialId' => $materialId ?: null,
            'type' => $type,
            'materials' => Material::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'current_stock'])
                ->map(fn (Material $material) => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'unit' => $material->unit->value,
                    'current_stock' => $material->current_stock,
                ])
                ->all(),
            // Only jobs still being worked on. Material cannot be consumed by
            // an order that was delivered a month ago.
            'orders' => Order::query()
                ->open()
                ->whereNotNull('order_no')
                ->with('customer:id,name')
                ->orderByDesc('order_date')
                ->limit(50)
                ->get()
                ->map(fn (Order $order) => [
                    'value' => $order->id,
                    'label' => "{$order->order_no} — {$order->customer->name}",
                ])
                ->all(),
            'types' => array_map(
                fn (MaterialMovementType $case) => ['value' => $case->value, 'label' => $case->label()],
                MaterialMovementType::cases()
            ),
            'issueTypes' => array_map(
                fn (MaterialMovementType $case) => ['value' => $case->value, 'label' => $case->label()],
                [MaterialMovementType::Out, MaterialMovementType::Wastage, MaterialMovementType::Return]
            ),
            'today' => CarbonImmutable::today()->toDateString(),
            'canAdjust' => $request->user()->can('stock.adjust'),
        ]);
    }

    public function issue(StockIssueRequest $request, IssueMaterial $issue): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $issue->handle(
                material: Material::findOrFail($validated['material_id']),
                quantity: number_format((float) $validated['quantity'], 3, '.', ''),
                movementDate: $validated['movement_date'],
                type: MaterialMovementType::from($validated['type']),
                order: isset($validated['order_id']) ? Order::find($validated['order_id']) : null,
                note: $validated['note'] ?? null,
                userId: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // More was asked for than is on the floor. Nothing was written.
            return back()->withInput()->with('error', $e->getMessage());
        }

        return to_route('stock.index')->with('success', 'মালামাল দেওয়া হয়েছে।');
    }

    public function count(StockCountRequest $request, AdjustStock $adjust): RedirectResponse
    {
        $validated = $request->validated();

        $movement = $adjust->handle(
            material: Material::findOrFail($validated['material_id']),
            countedStock: number_format((float) $validated['counted_stock'], 3, '.', ''),
            movementDate: $validated['movement_date'],
            note: $validated['note'] ?? null,
            userId: $request->user()->id,
        );

        // A count agreeing with the books writes nothing, and saying so is
        // more useful than a success message about a row that does not exist.
        return to_route('stock.index')->with(
            'success',
            $movement === null
                ? 'গণনা খাতার সাথে মিলে গেছে, কোনো সংশোধন লাগেনি।'
                : 'গুদামের হিসাব সংশোধন করা হয়েছে।'
        );
    }
}
