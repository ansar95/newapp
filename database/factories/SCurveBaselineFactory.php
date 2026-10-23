<?php

namespace Database\Factories;

use App\Enums\SCurveBaselineStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SCurveBaseline>
 */
class SCurveBaselineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'version' => 1,
            'kind' => SCurveKind::Initial,
            'status' => SCurveBaselineStatus::Draft,
            'granularity' => SCurveGranularity::Week,
            'established_on' => null,
            'effective_from' => null,
            'reason' => null,
            'created_by' => User::factory(),
        ];
    }
}
