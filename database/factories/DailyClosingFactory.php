<?php

namespace Database\Factories;

use App\Models\DailyClosing;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyClosing>
 */
class DailyClosingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'closing_date' => '2026-07-20',
            'opening_balance' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'net_amount' => 0,
            'expected_closing' => 0,
            'counted_cash' => 0,
            'difference' => 0,
            'credit_purchase_today' => 0,
            'total_payable' => 0,
            'total_receivable' => 0,
            'closed_by' => null,
            'closed_at' => null,
            'note' => null,
        ];
    }
}
