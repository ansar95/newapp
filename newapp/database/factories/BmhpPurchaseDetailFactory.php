<?php

namespace Database\Factories;

use App\Models\BmhpPurchase;
use App\Models\BmhpPurchaseDetail;
use App\Models\MasterBmhp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BmhpPurchaseDetail>
 */
class BmhpPurchaseDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bmhp_purchase_id' => BmhpPurchase::factory(),
            'master_bmhp_id' => MasterBmhp::factory(),
            'item_name' => ucfirst(fake()->words(2, true)),
            'unit' => 'Pcs',
            'quantity' => fake()->numberBetween(1, 100),
            'unit_price' => fake()->numberBetween(10, 500) * 1000,
            'notes' => null,
        ];
    }
}
