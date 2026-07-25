<?php

namespace App\Http\Controllers;

use App\Actions\Employees\RecordEmployeePayment;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Http\Requests\Employees\EmployeePaymentRequest;
use App\Models\Account;
use App\Models\Employee;
use App\Models\EmployeeLedger;
use App\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class EmployeeLedgerController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Who the shop owes, worst first. Balances come from one grouped query
     * rather than one per row.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
            ))
            ->with('trade:id,name')
            ->orderBy('name')
            ->get();

        $balances = $this->ledger->balancesFor($employees->pluck('id')->all());

        $rows = $employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'name' => $employee->name,
            'employee_code' => $employee->employee_code,
            'trade' => $employee->trade?->name,
            'wage_type' => $employee->wage_type->value,
            'balance' => $balances[$employee->id] ?? '0.00',
        ])
            // The person owed most sits at the top, since that is who asks first.
            ->sortByDesc(fn (array $row) => (float) $row['balance'])
            ->values()
            ->all();

        return Inertia::render('employee-ledger/index', [
            'employees' => $rows,
            'search' => $search,
            'totalOwed' => number_format(
                array_sum(array_map(fn (array $row) => max((float) $row['balance'], 0), $rows)),
                2, '.', ''
            ),
            'canPay' => $request->user()->can('employee_payment.record'),
        ]);
    }

    /**
     * One worker's account: what they have earned, what they have taken, and
     * the running balance between the two.
     */
    public function show(Request $request, Employee $employee): Response
    {
        $entries = EmployeeLedger::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (EmployeeLedger $entry) => [
                'id' => $entry->id,
                'entry_date' => $entry->entry_date->toDateString(),
                'type' => $entry->type->value,
                'direction' => $entry->direction->value,
                'amount' => $entry->amount,
                'payment_method' => $entry->payment_method?->value,
                'note' => $entry->note,
            ]);

        return Inertia::render('employee-ledger/show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee_code,
                'phone' => $employee->phone,
                'wage_type' => $employee->wage_type->value,
                'daily_rate' => $employee->daily_rate,
                'monthly_salary' => $employee->monthly_salary,
            ],
            'entries' => $entries,
            'balance' => $this->ledger->balanceFor($employee),
            'totals' => $this->totalsFor($employee),
            'paymentTypes' => $this->paymentTypeOptions(),
            'paymentMethods' => array_map(
                fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()],
                PaymentMethod::cases()
            ),
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
            'canPay' => $request->user()->can('employee_payment.record'),
        ]);
    }

    public function store(EmployeePaymentRequest $request, Employee $employee, RecordEmployeePayment $record): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $record->handle(
                employee: $employee,
                type: LedgerEntryType::from($validated['type']),
                amount: number_format((float) $validated['amount'], 2, '.', ''),
                entryDate: $validated['entry_date'],
                account: isset($validated['account_id']) ? Account::find($validated['account_id']) : null,
                paymentMethod: PaymentMethod::from($validated['payment_method'] ?? 'cash'),
                note: $validated['note'] ?? null,
                createdBy: $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // The cash box could not cover it. Nothing was written.
            return back()->with('error', $e->getMessage());
        }

        return to_route('employee-ledger.show', $employee)->with('success', 'হিসাব যোগ করা হয়েছে।');
    }

    /**
     * @return array{earned: string, taken: string}
     */
    private function totalsFor(Employee $employee): array
    {
        $row = EmployeeLedger::query()
            ->where('employee_id', $employee->id)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0) AS earned,
                COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END), 0) AS taken
            ")
            ->first();

        return [
            'earned' => number_format((float) $row->earned, 2, '.', ''),
            'taken' => number_format((float) $row->taken, 2, '.', ''),
        ];
    }

    /**
     * @return list<array{value: string, label: string, movesCash: bool}>
     */
    private function paymentTypeOptions(): array
    {
        return array_map(
            fn (LedgerEntryType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'movesCash' => in_array($type, [LedgerEntryType::Advance, LedgerEntryType::Tiffin, LedgerEntryType::Payout], true),
            ],
            [
                LedgerEntryType::Payout,
                LedgerEntryType::Advance,
                LedgerEntryType::Tiffin,
                LedgerEntryType::Bonus,
                LedgerEntryType::Fine,
            ]
        );
    }
}
