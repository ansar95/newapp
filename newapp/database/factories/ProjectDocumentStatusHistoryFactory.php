<?php

namespace Database\Factories;

use App\Enums\ProjectDocumentStatus;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectDocumentStatusHistory>
 */
class ProjectDocumentStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_document_id' => ProjectDocument::factory(),
            'from_status' => null,
            'to_status' => ProjectDocumentStatus::Draft,
            'version_number' => 1,
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }
}
