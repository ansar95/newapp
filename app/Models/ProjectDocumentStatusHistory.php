<?php

namespace App\Models;

use App\Enums\DocumentActorType;
use App\Enums\ProjectDocumentStatus;
use Database\Factories\ProjectDocumentStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_document_id',
    'from_status',
    'to_status',
    'version_number',
    'notes',
    'revision_reason',
    'actor_type',
    'changed_by',
    'changed_at',
])]
class ProjectDocumentStatusHistory extends Model
{
    /** @use HasFactory<ProjectDocumentStatusHistoryFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'actor_type' => DocumentActorType::Internal->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ProjectDocumentStatus::class,
            'to_status' => ProjectDocumentStatus::class,
            'actor_type' => DocumentActorType::class,
            'version_number' => 'integer',
            'changed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ProjectDocument::class, 'project_document_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
