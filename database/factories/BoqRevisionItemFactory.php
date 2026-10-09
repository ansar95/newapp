<?php

namespace Database\Factories;

use App\Enums\RevisionItemStatus;
use App\Models\BoqItem;
use App\Models\BoqRevision;
use App\Models\BoqRevisionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqRevisionItem>
 */
class BoqRevisionItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boq_revision_id' => BoqRevision::factory(),
            'boq_item_id' => BoqItem::factory(),
            'reason' => fake()->sentence(),
            'status' => RevisionItemStatus::Pending,
        ];
    }
}
