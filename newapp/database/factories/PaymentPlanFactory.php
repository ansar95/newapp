<?php

namespace Database\Factories;

use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Models\Boq;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentPlan>
 */
class PaymentPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boq_id' => Boq::factory(),
            'reference_code' => 'PP-'.fake()->unique()->numerify('######'),
            'type' => PaymentPlanType::Planned,
            'planned_date' => now()->addDays(7)->toDateString(),
            'amount' => fake()->randomFloat(2, 1000000, 50000000),
            'status' => PaymentPlanStatus::Planned,
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function readyToPay(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentPlanType::ReadyToPay,
            'status' => PaymentPlanStatus::ReadyToPay,
        ]);
    }
}
