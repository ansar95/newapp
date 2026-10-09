<?php

namespace Database\Factories;

use App\Enums\MasterBmhpStatus;
use App\Models\MasterBmhp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MasterBmhp>
 */
class MasterBmhpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BMHP-'.fake()->unique()->numerify('####'),
            'name' => ucfirst(fake()->words(2, true)),
            'category' => fake()->randomElement(MasterBmhp::categoryOptions()),
            'unit' => fake()->randomElement(['Zak', 'Lembar', 'Batang', 'Box', 'Pcs']),
            'description' => fake()->optional()->sentence(),
            'status' => MasterBmhpStatus::Active,
            'created_by' => User::factory(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => MasterBmhpStatus::Inactive,
        ]);
    }
}
