<?php

namespace Database\Factories;

use App\Enums\ProjectAssetStatus;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectAsset>
 */
class ProjectAssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'asset_id' => Asset::factory()->available(),
            'assigned_at' => now(),
            'released_at' => null,
            'notes' => fake()->optional()->sentence(),
            'status' => ProjectAssetStatus::InUse,
            'assigned_by' => User::factory(),
            'released_by' => null,
        ];
    }

    public function released(): static
    {
        return $this->state(fn () => [
            'status' => ProjectAssetStatus::Released,
            'released_at' => now(),
            'released_by' => User::factory(),
        ]);
    }
}
