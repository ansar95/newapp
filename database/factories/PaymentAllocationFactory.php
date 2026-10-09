<?php

namespace Database\Factories;

use App\Models\BoqItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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
            'payment_id' => Payment::factory(),
            'boq_item_id' => BoqItem::factory(),
            'allocated_amount' => fake()->randomFloat(2, 100000, 5000000),
            'notes' => null,
        ];
    }
}
