<?php

namespace Database\Factories;

use App\Models\BmhpAllocation;
use App\Models\BmhpPurchaseDetail;
use App\Models\BoqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BmhpAllocation>
 */
class BmhpAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bmhp_purchase_detail_id' => BmhpPurchaseDetail::factory(),
            'boq_item_id' => BoqItem::factory(),
            'allocated_quantity' => null,
            'allocated_amount' => fake()->numberBetween(1, 100) * 1000,
            'notes' => null,
        ];
    }
}
