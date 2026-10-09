<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_plan_id' => PaymentPlan::factory()->readyToPay(),
            'payment_code' => 'PAY-'.fake()->unique()->numerify('######'),
            'payment_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 1000000, 50000000),
            'payment_method' => 'bank_transfer',
            'reference_number' => fake()->optional()->bothify('TRX-#####'),
            'notes' => fake()->optional()->sentence(),
            'status' => PaymentStatus::Paid,
            'paid_by' => User::factory(),
        ];
    }
}
