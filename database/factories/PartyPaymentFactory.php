<?php

namespace Database\Factories;

use App\Enums\CashPaymentMethod;
use App\Enums\PartyType;
use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\PartyPayment;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyPayment>
 */
class PartyPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_type' => PartyType::Supplier,
            'party_id' => Supplier::factory(),
            'direction' => TransactionDirection::Out,
            'payment_date' => '2026-07-20',
            'amount' => '5000.00',
            'account_id' => Account::factory(),
            'payment_method' => CashPaymentMethod::Cash,
            'reference_no' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
