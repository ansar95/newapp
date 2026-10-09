<?php

namespace Database\Factories;

use App\Enums\ClientBillingStatus;
use App\Models\ClientBilling;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientBilling>
 */
class ClientBillingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_term_id' => ProjectTerm::factory()->ready(),
            'project_id' => fn (array $attributes) => ProjectTerm::query()->find($attributes['project_term_id'])?->project_id,
            'billing_number' => 'BIL-'.fake()->unique()->numerify('######'),
            'billing_date' => now()->toDateString(),
            'billing_amount' => fake()->randomFloat(2, 1000000, 10000000),
            'invoice_number' => null,
            'invoice_date' => null,
            'due_date' => null,
            'description' => fake()->optional()->sentence(),
            'status' => ClientBillingStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => ClientBillingStatus::Sent,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'invoice_date' => now()->toDateString(),
            'sent_at' => now(),
        ]);
    }
}
