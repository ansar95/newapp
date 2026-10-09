<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('###'),
            'name' => fake()->sentence(3),
            'client_name' => fake()->company(),
            'contract_number' => fake()->bothify('###/KTR/??/####'),
            'contract_value' => fake()->numberBetween(100, 5000) * 1_000_000,
            'description' => fake()->optional()->paragraph(),
            'start_date' => now()->toDateString(),
            'target_end_date' => now()->addMonths(6)->toDateString(),
            'status' => ProjectStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
