<?php

namespace Database\Factories;

use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermReadiness;
use App\Enums\ProjectTermTriggerType;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTerm>
 */
class ProjectTermFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'term_number' => fake()->unique()->numberBetween(1, 30000),
            'term_name' => 'Termin '.fake()->numberBetween(1, 9),
            'calculation_type' => ProjectTermCalculationType::FixedAmount,
            'percentage' => null,
            'amount' => fake()->randomFloat(2, 10000000, 200000000),
            'trigger_type' => ProjectTermTriggerType::Manual,
            'trigger_value' => null,
            'due_date' => null,
            'notes' => null,
            'readiness' => ProjectTermReadiness::NotReady,
            'created_by' => User::factory(),
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'readiness' => ProjectTermReadiness::Ready,
        ]);
    }
}
