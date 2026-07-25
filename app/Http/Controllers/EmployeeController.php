<?php

namespace App\Http\Controllers;

use App\Actions\Employees\DeleteEmployee;
use App\Actions\Employees\SaveEmployee;
use App\Enums\WageType;
use App\Http\Requests\MasterData\EmployeeRequest;
use App\Models\Employee;
use App\Models\Shop;
use App\Models\Trade;
use App\Support\ReferencedRecordException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('employees/index', [
            'employees' => Employee::query()
                ->with(['trade:id,name', 'shop:id,name'])
                ->when($search !== '', fn ($query) => $query->where(
                    fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                ))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'search' => $search,
            'canManage' => $request->user()->can('employees.manage'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('employees/create', [
            ...$this->formOptions(),
            'nextCode' => $this->nextEmployeeCode(),
        ]);
    }

    public function store(EmployeeRequest $request, SaveEmployee $save): RedirectResponse
    {
        $save->handle($request->validated());

        return to_route('employees.index')->with('success', 'কর্মী যোগ করা হয়েছে।');
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('employees/edit', [
            'employee' => $employee,
            ...$this->formOptions(),
        ]);
    }

    public function update(EmployeeRequest $request, Employee $employee, SaveEmployee $save): RedirectResponse
    {
        $save->handle($request->validated(), $employee);

        return to_route('employees.index')->with('success', 'কর্মীর তথ্য বদলানো হয়েছে।');
    }

    public function destroy(Employee $employee, DeleteEmployee $delete): RedirectResponse
    {
        try {
            $delete->handle($employee);
        } catch (ReferencedRecordException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'কর্মী মুছে ফেলা হয়েছে।');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'wageTypes' => array_map(
                fn (WageType $type) => ['value' => $type->value, 'label' => $type->label()],
                WageType::cases()
            ),
            'trades' => Trade::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'default_daily_rate'])
                ->map(fn (Trade $trade) => [
                    'value' => $trade->id,
                    'label' => $trade->name,
                    // Lets the form prefill the rate when a trade is picked.
                    'defaultDailyRate' => $trade->default_daily_rate,
                ])
                ->all(),
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
        ];
    }

    /**
     * Suggests the next code so nobody has to invent one at the counter.
     * It is only a default; the field stays editable and unique-checked.
     */
    private function nextEmployeeCode(): string
    {
        $last = Employee::withTrashed()
            ->where('employee_code', 'like', 'EMP-%')
            ->orderByDesc('id')
            ->value('employee_code');

        $number = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'EMP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
