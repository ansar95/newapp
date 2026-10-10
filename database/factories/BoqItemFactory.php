<?php

namespace Database\Factories;

use App\Models\BoqCogs;
use App\Models\BoqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqItem>
 */
class BoqItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 100);
        $unitPrice = fake()->randomFloat(2, 10000, 500000);

        return [
            'boq_cogs_id' => BoqCogs::factory(),
            'code' => 'ITEM-'.fake()->unique()->numerify('###'),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'quantity' => $quantity,
            'unit' => fake()->randomElement(['unit', 'm2', 'ls', 'set']),
            'unit_price' => $unitPrice,
            'total_price' => round($quantity * $unitPrice, 2),
            'progress_percentage' => 0,
            'sort_order' => 0,
        ];
    }

    public function splitPrice(float $materialUnitPrice, float $serviceUnitPrice): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit_price' => null,
            'material_unit_price' => $materialUnitPrice,
            'service_unit_price' => $serviceUnitPrice,
        ]);
    }
}
