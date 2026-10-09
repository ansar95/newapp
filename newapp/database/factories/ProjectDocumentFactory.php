<?php

namespace Database\Factories;

use App\Enums\ProjectDocumentStatus;
use App\Models\DocumentType;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDocument>
 */
class ProjectDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'document_type_id' => DocumentType::factory(),
            'reference_type' => null,
            'reference_id' => null,
            'document_number' => 'DOC-'.fake()->unique()->numerify('######'),
            'document_title' => fake()->sentence(3),
            'description' => null,
            'current_status' => ProjectDocumentStatus::Draft,
            'current_version' => 0,
            'created_by' => User::factory(),
        ];
    }
}
