<?php

namespace App\Models;

use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermReadiness;
use App\Enums\ProjectTermTriggerType;
use App\Models\Concerns\ResolvesWithActiveProject;
use Carbon\CarbonInterface;
use Database\Factories\ProjectTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'term_number',
    'term_name',
    'calculation_type',
    'percentage',
    'amount',
    'trigger_type',
    'trigger_value',
    'trigger_progress',
    'due_date',
    'notes',
    'readiness',
    'created_by',
])]
class ProjectTerm extends Model
{
    /** @use HasFactory<ProjectTermFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'trigger_type' => ProjectTermTriggerType::Manual->value,
        'readiness' => ProjectTermReadiness::NotReady->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculation_type' => ProjectTermCalculationType::class,
            'trigger_type' => ProjectTermTriggerType::class,
            'readiness' => ProjectTermReadiness::class,
            'term_number' => 'integer',
            'percentage' => 'decimal:4',
            'amount' => 'decimal:2',
            'trigger_progress' => 'decimal:2',
            'due_date' => 'date',
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

    public function billings(): HasMany
    {
        return $this->hasMany(ClientBilling::class)->orderBy('billing_date')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->readiness === ProjectTermReadiness::Cancelled;
    }

    public function isReady(): bool
    {
        return $this->readiness === ProjectTermReadiness::Ready;
    }

    public function isPercentage(): bool
    {
        return $this->calculation_type === ProjectTermCalculationType::Percentage;
    }

    /**
     * Whether the term's billing condition is met: project progress reached the PROGRESS threshold,
     * or the target date of a DATE term has arrived. Manual and milestone terms never trigger.
     */
    public function isTriggerReached(float $projectProgress, ?CarbonInterface $today = null): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        return match ($this->trigger_type) {
            ProjectTermTriggerType::Progress => $this->trigger_progress !== null
                && round($projectProgress, 2) >= (float) $this->trigger_progress,
            ProjectTermTriggerType::Date => $this->due_date !== null
                && $this->due_date->lte(($today ?? now())->copy()->startOfDay()),
            default => false,
        };
    }

    public function triggerDescription(): string
    {
        $threshold = (float) $this->trigger_progress;

        return match (true) {
            $this->trigger_type === ProjectTermTriggerType::Progress && $this->trigger_progress !== null => 'Progress fisik ≥ '.percent_format($threshold, floor($threshold) === $threshold ? 0 : 2),
            $this->trigger_type === ProjectTermTriggerType::Date && $this->due_date !== null => 'Tanggal '.date_id($this->due_date),
            default => $this->trigger_type->label(),
        };
    }

    public function displayName(): string
    {
        return 'Termin '.$this->term_number.($this->term_name !== '' ? ' — '.$this->term_name : '');
    }
}
