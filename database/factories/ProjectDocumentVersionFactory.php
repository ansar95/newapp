<?php

namespace Database\Factories;

use App\Models\ProjectDocument;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProjectDocumentVersion>
 */
class ProjectDocumentVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_document_id' => ProjectDocument::factory(),
            'version_number' => 1,
            'file_path' => 'project-documents/'.Str::uuid().'.pdf',
            'original_filename' => 'dokumen.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum_sha256' => null,
            'notes' => null,
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
