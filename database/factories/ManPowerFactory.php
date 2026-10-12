<?php

namespace Database\Factories;

use App\Models\ManPower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManPower>
 */
class ManPowerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'MP-'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'position' => fake()->randomElement(['Tukang', 'Helper', 'Mandor', 'Teknisi']),
            'daily_wage' => fake()->randomElement([100000, 150000, 200000]),
            'phone' => fake()->optional()->numerify('08##########'),
            'is_active' => true,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
