<?php

namespace App\Models;

use App\Enums\ProjectAssetStatus;
use Database\Factories\ProjectAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'asset_id',
    'assigned_at',
    'released_at',
    'notes',
    'responsible_name',
    'responsible_position',
    'status',
    'assigned_by',
    'released_by',
])]
class ProjectAsset extends Model
{
    /** @use HasFactory<ProjectAssetFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProjectAssetStatus::InUse->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectAssetStatus::class,
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isActive(): bool
    {
        return $this->status === ProjectAssetStatus::InUse;
    }
}
