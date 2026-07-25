<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => null,
            'category_id' => ExpenseCategory::factory(),
            'expense_date' => '2026-07-20',
            'amount' => 1500,
            'paid_to' => null,
            'payment_method' => PaymentMethod::Cash,
            'account_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
