<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\MarkDailyAttendance;
use App\Enums\AttendanceStatus;
use App\Enums\WageType;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * The sheet for one day. Every active employee appears, already carrying
     * whatever was saved for that date, so re-opening the screen shows the
     * day as it stands rather than a blank form.
     */
    public function index(Request $request): Response
    {
        $date = $this->resolveDate($request);
        $shopId = $request->integer('shop_id') ?: null;

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId))
            ->with('trade:id,name')
            ->orderBy('name')
            ->get();

        $existing = Attendance::query()
            ->where('work_date', $date)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        // The fraction of the daily wage a night earns when an employee has no
        // flat override. Used only for the on-screen preview; the saved amount
        // is computed by MarkDailyAttendance.
        $nightMultiplier = Setting::read('payroll.night_multiplier', '0.5');

        return Inertia::render('attendance/index', [
            'workDate' => $date,
            'shopId' => $shopId,
            'shops' => Shop::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name])
                ->all(),
            'employees' => $employees->map(function (Employee $employee) use ($existing, $nightMultiplier) {
                $saved = $existing->get($employee->id);

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_code' => $employee->employee_code,
                    'trade' => $employee->trade?->name,
                    'wage_type' => $employee->wage_type->value,
                    'daily_rate' => $employee->daily_rate,
                    'night_rate' => $this->nightPreviewRate($employee, $nightMultiplier),
                    'status' => $saved?->status->value,
                    'in_time' => $saved?->in_time ? substr($saved->in_time, 0, 5) : null,
                    'out_time' => $saved?->out_time ? substr($saved->out_time, 0, 5) : null,
                    'overtime_hours' => $saved ? (string) $saved->overtime_hours : '0.00',
                    'overtime_rate' => $saved ? (string) $saved->overtime_rate : '0.00',
                    'note' => $saved?->note,
                ];
            })->all(),
            'statuses' => array_map(
                fn (AttendanceStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'earnsWage' => $status->earnsWage(),
                    'hasNight' => $status->hasNight(),
                ],
                AttendanceStatus::cases()
            ),
            'alreadySaved' => $existing->isNotEmpty(),
            'canMark' => $request->user()->can('attendance.mark'),
        ]);
    }

    public function store(MarkAttendanceRequest $request, MarkDailyAttendance $markAttendance): RedirectResponse
    {
        $validated = $request->validated();

        $markAttendance->handle(
            workDate: $validated['work_date'],
            rows: $validated['rows'],
            markedBy: $request->user()->id,
            shopId: $validated['shop_id'] ?? null,
        );

        return to_route('attendance.index', array_filter([
            'date' => $validated['work_date'],
            'shop_id' => $validated['shop_id'] ?? null,
        ]))->with('success', 'হাজিরা সংরক্ষণ করা হয়েছে।');
    }

    /**
     * The night allowance shown in the preview: the flat override when set,
     * otherwise the configured fraction of the daily wage. Monthly staff earn
     * no night allowance, so they preview as zero.
     */
    private function nightPreviewRate(Employee $employee, string $multiplier): string
    {
        if ($employee->wage_type === WageType::Monthly) {
            return '0.00';
        }

        if ($employee->night_rate !== null) {
            return bcadd((string) $employee->night_rate, '0', 2);
        }

        return bcmul((string) $employee->daily_rate, $multiplier, 2);
    }

    /**
     * Defaults to today. A future date is pulled back rather than refused,
     * since it usually means a mistyped query string.
     */
    private function resolveDate(Request $request): string
    {
        $today = CarbonImmutable::today();

        $date = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString()),
            $today,
            report: false
        );

        return $date->greaterThan($today) ? $today->toDateString() : $date->toDateString();
    }
}
