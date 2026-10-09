<?php

namespace Database\Factories;

use App\Enums\BoqStatus;
use App\Models\Boq;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boq>
 */
class BoqFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => 'BOQ-'.fake()->unique()->bothify('PRJ###'),
            'revision_number' => 1,
            'status' => BoqStatus::Draft,
            'submitted_by' => null,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BoqStatus::Approved,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);
    }
}
