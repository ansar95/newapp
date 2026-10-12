<?php

namespace Database\Factories;

use App\Models\ManPower;
use App\Models\Project;
use App\Models\ProjectManPower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectManPower>
 */
class ProjectManPowerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'man_power_id' => ManPower::factory(),
            'position' => 'Tukang',
            'daily_wage' => 100000,
            'work_days' => 10,
            'start_date' => now()->toDateString(),
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
