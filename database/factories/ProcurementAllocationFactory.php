<?php

namespace Database\Factories;

use App\Models\BoqItem;
use App\Models\ProcurementAllocation;
use App\Models\ProcurementRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementAllocation>
 */
class ProcurementAllocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'procurement_request_item_id' => ProcurementRequestItem::factory(),
            'boq_item_id' => BoqItem::factory(),
            'allocated_quantity' => null,
            'allocated_amount' => fake()->numberBetween(1, 100) * 1000,
            'notes' => null,
        ];
    }
}
