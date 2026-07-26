<?php

namespace Database\Factories;

use App\Enums\CncJobStatus;
use App\Enums\CncMaterialBy;
use App\Enums\CncRateType;
use App\Models\CncJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CncJob>
 */
class CncJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_no' => 'CNC-2607-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'customer_id' => null,
            'customer_name' => 'বাইরের কারিগর',
            'customer_phone' => null,
            'order_id' => null,
            'job_date' => '2026-07-20',
            'description' => 'দরজার নকশা',
            'material_by' => CncMaterialBy::Customer,
            'rate_type' => CncRateType::PerSqft,
            'quantity' => '20.00',
            'rate' => '150.00',
            'total_amount' => '3000.00',
            'paid_amount' => 0,
            'due_amount' => '3000.00',
            'machine_hours' => 0,
            'operator_id' => null,
            'status' => CncJobStatus::Pending,
            'delivery_date' => null,
            'note' => null,
        ];
    }

    public function withTotals(string $total, string $paid = '0.00'): static
    {
        return $this->state(fn () => [
            'total_amount' => $total,
            'paid_amount' => $paid,
            'due_amount' => bcsub($total, $paid, 2),
        ]);
    }

    public function status(CncJobStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
