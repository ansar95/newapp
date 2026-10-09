<?php

namespace Database\Factories;

use App\Enums\RevisionStatus;
use App\Models\Boq;
use App\Models\BoqRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqRevision>
 */
class BoqRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $revisionNumber = fake()->numberBetween(1, 9);

        return [
            'boq_id' => Boq::factory(),
            'revision_code' => 'BOQ-REV'.fake()->unique()->numerify('##'),
            'revision_number' => $revisionNumber,
            'reason' => fake()->sentence(),
            'status' => RevisionStatus::Open,
            'created_by' => User::factory(),
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
