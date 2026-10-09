<?php

namespace App\Models;

use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermReadiness;
use App\Enums\ProjectTermTriggerType;
use App\Models\Concerns\ResolvesWithActiveProject;
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

    public function displayName(): string
    {
        return 'Termin '.$this->term_number.($this->term_name !== '' ? ' — '.$this->term_name : '');
    }
}
