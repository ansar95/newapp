<?php

namespace App\Models;

use App\Enums\DocumentReferenceType;
use App\Enums\ProjectDocumentStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Carbon\CarbonInterface;
use Database\Factories\ProjectDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'project_id',
    'document_type_id',
    'type_label',
    'reference_type',
    'reference_id',
    'document_number',
    'document_title',
    'description',
    'current_status',
    'current_version',
    'approved_by',
    'approved_at',
    'approval_notes',
    'approved_version',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
    'created_by',
])]
class ProjectDocument extends Model
{
    /** @use HasFactory<ProjectDocumentFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'current_status' => ProjectDocumentStatus::Draft->value,
        'current_version' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_status' => ProjectDocumentStatus::class,
            'current_version' => 'integer',
            'approved_version' => 'integer',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function activeProjectRelation(): string
    {
        return 'project';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function typeName(): string
    {
        if (filled($this->type_label)) {
            return $this->type_label;
        }

        return $this->documentType?->name ?? '-';
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProjectDocumentVersion::class)->orderBy('version_number');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ProjectDocumentStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public static function projectAcceptsDocuments(Project $project): bool
    {
        return in_array($project->status, [ProjectStatus::Active, ProjectStatus::Completed], true);
    }

    public static function assertProjectAcceptsDocuments(Project $project): void
    {
        if (! self::projectAcceptsDocuments($project)) {
            throw BusinessRuleException::make('Dokumen hanya dapat dikelola pada project berstatus ACTIVE atau COMPLETED.');
        }
    }

    public function billing(): ?ClientBilling
    {
        if ($this->reference_type !== DocumentReferenceType::Billing->value) {
            return null;
        }

        $reference = $this->reference;

        return $reference instanceof ClientBilling ? $reference : null;
    }

    public function referenceLabel(): ?string
    {
        $billing = $this->billing();

        if ($billing === null) {
            return null;
        }

        return ($billing->invoice_number ?? $billing->billing_number).($billing->term ? ' · '.$billing->term->displayName() : '');
    }

    public function activeVersion(): ?ProjectDocumentVersion
    {
        return $this->versions->firstWhere('version_number', $this->current_version);
    }

    public function statusSince(): ?CarbonInterface
    {
        return $this->histories->last()?->changed_at;
    }

    public function firstSubmittedAt(): ?CarbonInterface
    {
        return $this->histories->firstWhere('to_status', ProjectDocumentStatus::Submitted)?->changed_at;
    }

    public function revisionCount(): int
    {
        return $this->histories->where('to_status', ProjectDocumentStatus::RevisionRequired)->count();
    }

    public function latestRevisionRequest(): ?ProjectDocumentStatusHistory
    {
        return $this->histories->where('to_status', ProjectDocumentStatus::RevisionRequired)->last();
    }

    /**
     * A revision can only be resubmitted once a version newer than the one under review has been uploaded.
     */
    public function hasVersionAfterRevisionRequest(): bool
    {
        $request = $this->latestRevisionRequest();

        return $request !== null && $this->current_version > (int) $request->version_number;
    }

    /**
     * Seconds spent in the current status, or null once the document reached a final status.
     */
    public function waitingSeconds(?CarbonInterface $now = null): ?int
    {
        $since = $this->statusSince();

        if ($since === null || $this->current_status->isLocked()) {
            return null;
        }

        return (int) max(0, $since->diffInSeconds($now ?? now()));
    }

    /**
     * Next sequential document number (DOC-0001, DOC-0002, ...).
     */
    public static function nextDocumentNumber(): string
    {
        $max = 0;

        foreach (self::query()->where('document_number', 'like', 'DOC-%')->lockForUpdate()->pluck('document_number') as $number) {
            if (preg_match('/^DOC-(\d+)$/i', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'DOC-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
