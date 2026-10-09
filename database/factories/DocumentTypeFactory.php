<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DOC-'.fake()->unique()->bothify('???###'),
            'name' => fake()->words(2, true),
            'reference_type' => null,
            'description' => null,
            'is_active' => true,
            'sort_order' => 10,
        ];
    }
}
