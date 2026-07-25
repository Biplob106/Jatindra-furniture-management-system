<?php

namespace Database\Factories;

use App\Enums\CashPaymentMethod;
use App\Enums\TransactionDirection;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'txn_date' => '2026-07-20',
            'shop_id' => null,
            'account_id' => Account::factory(),
            'direction' => TransactionDirection::In,
            'amount' => 1000,
            'source_type' => TransactionSource::Adjustment,
            'source_id' => null,
            'party_type' => null,
            'party_id' => null,
            'payment_method' => CashPaymentMethod::Cash,
            'note' => null,
            'created_by' => null,
        ];
    }
}
