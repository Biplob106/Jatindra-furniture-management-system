<?php

namespace Database\Factories;

use App\Models\PartyPayment;
use App\Models\PaymentAllocation;
use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_payment_id' => PartyPayment::factory(),
            'allocatable_type' => Purchase::class,
            'allocatable_id' => Purchase::factory(),
            'allocated_amount' => '5000.00',
        ];
    }
}
