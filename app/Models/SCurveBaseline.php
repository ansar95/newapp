<?php

namespace App\Models;

use App\Enums\SCurveBaselineStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use Database\Factories\SCurveBaselineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'version',
    'kind',
    'status',
    'granularity',
    'established_on',
    'effective_from',
    'reason',
    'created_by',
])]
class SCurveBaseline extends Model
{
    /** @use HasFactory<SCurveBaselineFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SCurveBaselineStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SCurveKind::class,
            'status' => SCurveBaselineStatus::class,
            'granularity' => SCurveGranularity::class,
            'established_on' => 'date',
            'effective_from' => 'date',
            'version' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(SCurvePeriod::class)->orderBy('period_start');
    }

    public function itemSchedules(): HasMany
    {
        return $this->hasMany(SCurveItemSchedule::class);
    }

    public function isEstablished(): bool
    {
        return $this->status === SCurveBaselineStatus::Established;
    }
}
