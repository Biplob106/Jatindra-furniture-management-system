<?php

namespace App\Http\Controllers;

use App\Actions\Materials\DeleteMaterial;
use App\Actions\Materials\SaveMaterial;
use App\Enums\MaterialCategory;
use App\Enums\MaterialUnit;
use App\Http\Requests\MasterData\MaterialRequest;
use App\Models\Material;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaterialController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $lowOnly = $request->boolean('low');

        return Inertia::render('materials/index', [
            'materials' => Material::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->when($lowOnly, fn ($query) => $query->lowStock())
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'low' => $lowOnly,
            'lowCount' => Material::query()->active()->lowStock()->count(),
            'canManage' => $request->user()->can('materials.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('materials/create', $this->options());
    }

    public function store(MaterialRequest $request, SaveMaterial $save): RedirectResponse
    {
        $save->handle($request->validated());

        return to_route('materials.index')->with('success', 'মালামাল যোগ করা হয়েছে।');
    }

    public function edit(Material $material): Response
    {
        return Inertia::render('materials/edit', [
            'material' => $material,
            ...$this->options(),
        ]);
    }

    public function update(MaterialRequest $request, Material $material, SaveMaterial $save): RedirectResponse
    {
        $save->handle($request->validated(), $material);

        return to_route('materials.index')->with('success', 'মালামালের তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Material $material, DeleteMaterial $delete): RedirectResponse
    {
        try {
            $delete->handle($material);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'মালামাল মুছে ফেলা হয়েছে।');
    }

    /**
     * @return array{categories: list<array{value: string, label: string}>, units: list<array{value: string, label: string}>}
     */
    private function options(): array
    {
        return [
            'categories' => array_map(
                fn (MaterialCategory $category) => ['value' => $category->value, 'label' => $category->label()],
                MaterialCategory::cases()
            ),
            'units' => array_map(
                fn (MaterialUnit $unit) => ['value' => $unit->value, 'label' => $unit->label()],
                MaterialUnit::cases()
            ),
        ];
    }
}
