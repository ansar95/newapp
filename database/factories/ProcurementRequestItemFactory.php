<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcurementRequestItem>
 */
class ProcurementRequestItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'procurement_request_id' => ProcurementRequest::factory(),
            'item_name' => ucfirst(fake()->words(2, true)),
            'unit' => 'Pcs',
            'quantity' => fake()->numberBetween(1, 100),
            'unit_price' => fake()->numberBetween(10, 500) * 1000,
            'notes' => null,
        ];
    }
}
