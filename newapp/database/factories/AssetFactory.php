<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'AST-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(Asset::categoryOptions()),
            'brand' => fake()->optional()->company(),
            'model' => fake()->optional()->bothify('Model-###'),
            'unit' => 'Unit',
            'source_type' => AssetSourceType::Existing,
            'status' => AssetStatus::Available,
            'condition' => AssetCondition::Good,
            'purchase_year' => fake()->optional()->numberBetween(2018, (int) now()->year),
            'description' => fake()->optional()->sentence(),
            'request_number' => null,
            'request_date' => null,
            'request_letter_file' => null,
            'request_description' => null,
            'requested_by' => null,
            'request_status' => null,
            'created_by' => User::factory(),
        ];
    }

    public function existing(): static
    {
        return $this->state(fn () => [
            'source_type' => AssetSourceType::Existing,
            'status' => AssetStatus::Available,
            'request_number' => null,
            'request_date' => null,
            'request_letter_file' => null,
            'request_description' => null,
            'requested_by' => null,
            'request_status' => null,
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn () => [
            'source_type' => AssetSourceType::Purchase,
            'status' => AssetStatus::Requested,
            'request_number' => 'SPP-'.fake()->unique()->numerify('####'),
            'request_date' => now()->toDateString(),
            'request_description' => 'Permohonan pembelian asset.',
            'request_status' => AssetRequestStatus::Requested,
            'requested_by' => User::factory(),
        ]);
    }

    public function available(): static
    {
        return $this->state(fn () => [
            'status' => AssetStatus::Available,
        ]);
    }

    public function inUse(): static
    {
        return $this->state(fn () => [
            'status' => AssetStatus::InUse,
        ]);
    }

    public function requested(): static
    {
        return $this->state(fn () => [
            'status' => AssetStatus::Requested,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => AssetStatus::Inactive,
        ]);
    }
}
