<?php

namespace App\Models;

use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ProjectDocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_document_id',
    'version_number',
    'file_path',
    'original_filename',
    'mime_type',
    'file_size',
    'checksum_sha256',
    'notes',
    'uploaded_by',
    'uploaded_at',
])]
class ProjectDocumentVersion extends Model
{
    public const DISK = 'local';

    /** @use HasFactory<ProjectDocumentVersionFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var list<string>
     */
    protected $hidden = ['file_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    protected function activeProjectRelation(): string
    {
        return 'document.project';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProjectDocument::class, 'project_document_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function label(): string
    {
        return 'V'.$this->version_number;
    }

    public function readableSize(): string
    {
        return match (true) {
            $this->file_size >= 1_048_576 => number_format($this->file_size / 1_048_576, 1, ',', '.').' MB',
            $this->file_size >= 1024 => number_format($this->file_size / 1024, 0, ',', '.').' KB',
            default => $this->file_size.' B',
        };
    }
}
