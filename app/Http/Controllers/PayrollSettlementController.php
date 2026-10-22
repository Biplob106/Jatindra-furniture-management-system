<?php

namespace App\Http\Controllers;

use App\Actions\Payroll\ReversePayrollSettlement;
use App\Actions\Payroll\SettlePayroll;
use App\Http\Requests\Payroll\SettlePayrollRequest;
use App\Models\PayrollSettlement;
use Illuminate\Http\RedirectResponse;

/**
 * Finalizing and reversing a payroll period. Both go back to the report with
 * the same filters so the page reloads showing the new settled state.
 */
class PayrollSettlementController extends Controller
{
    public function store(SettlePayrollRequest $request, SettlePayroll $settle): RedirectResponse
    {
        $data = $request->validated();

        $result = $settle->handle(
            from: $data['from'],
            to: $data['to'],
            shopId: $data['shop_id'] ?? null,
            settledBy: $request->user()->id,
        );

        return $this->backToReport($data, "{$this->digits($result['settled'])} জন কর্মীর পে-রোল চূড়ান্ত করা হয়েছে।");
    }

    public function reverse(SettlePayrollRequest $request, ReversePayrollSettlement $reverse): RedirectResponse
    {
        $data = $request->validated();

        $active = PayrollSettlement::query()
            ->where('period_start', $data['from'])
            ->where('period_end', $data['to'])
            ->when($data['shop_id'] ?? null, fn ($query, $shopId) => $query->where('shop_id', $shopId))
            ->whereNull('reversed_at')
            ->get();

        foreach ($active as $settlement) {
            $reverse->handle($settlement, $request->user()->id);
        }

        return $this->backToReport($data, 'এই সময়ের চূড়ান্ত পে-রোল বাতিল করা হয়েছে।');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function backToReport(array $data, string $message): RedirectResponse
    {
        return to_route('reports.payroll', array_filter([
            'from' => $data['from'],
            'to' => $data['to'],
            'shop_id' => $data['shop_id'] ?? null,
        ]))->with('success', $message);
    }

    private function digits(int $number): string
    {
        return strtr((string) $number, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
    }
}
