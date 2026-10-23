<?php

namespace Database\Factories;

use App\Models\ProgressSnapshot;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgressSnapshot>
 */
class ProgressSnapshotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'snapshot_date' => now()->toDateString(),
            'progress_percentage' => 0,
        ];
    }
}
