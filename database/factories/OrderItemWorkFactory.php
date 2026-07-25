<?php

namespace Database\Factories;

use App\Enums\OrderItemWorkStatus;
use App\Models\Employee;
use App\Models\OrderItem;
use App\Models\OrderItemWork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItemWork>
 */
class OrderItemWorkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'employee_id' => Employee::factory(),
            'trade_id' => null,
            'work_type' => null,
            'agreed_amount' => 3000,
            'assigned_date' => '2026-07-20',
            'started_at' => null,
            'completed_at' => null,
            'status' => OrderItemWorkStatus::Assigned,
            'note' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => OrderItemWorkStatus::Done,
            'completed_at' => '2026-07-25 17:00:00',
        ]);
    }
}
