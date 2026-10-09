<?php

namespace Database\Factories;

use App\Enums\BmhpPurchaseStatus;
use App\Models\BmhpPurchase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BmhpPurchase>
 */
class BmhpPurchaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'purchase_number' => 'PB-'.fake()->unique()->numerify('####'),
            'purchase_date' => now()->toDateString(),
            'supplier' => fake()->company(),
            'invoice_number' => fake()->optional()->bothify('INV-####'),
            'notes' => null,
            'total_amount' => 0,
            'status' => BmhpPurchaseStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => BmhpPurchaseStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BmhpPurchaseStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Salah input.',
        ]);
    }
}
