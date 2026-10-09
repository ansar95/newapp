<?php

namespace Database\Factories;

use App\Models\Boq;
use App\Models\BoqCogs;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoqCogs>
 */
class BoqCogsFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'boq_id' => Boq::factory(),
            'code' => 'COGS-'.fake()->unique()->numerify('###'),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
