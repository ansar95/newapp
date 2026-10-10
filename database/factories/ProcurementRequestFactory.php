<?php

namespace Database\Factories;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementRequest>
 */
class ProcurementRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'request_number' => 'PR-'.fake()->unique()->numerify('####'),
            'type' => ProcurementType::Bmhp,
            'request_date' => now()->toDateString(),
            'supplier' => fake()->company(),
            'notes' => null,
            'estimated_total' => 0,
            'status' => ProcurementStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function type(ProcurementType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => ProcurementStatus::Paid,
            'approved_at' => now(),
            'paid_at' => now(),
            'payment_date' => now()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ProcurementStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Salah input.',
        ]);
    }
}
