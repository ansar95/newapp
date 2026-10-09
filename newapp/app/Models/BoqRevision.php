<?php

namespace App\Models;

use App\Enums\RevisionStatus;
use Database\Factories\BoqRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'boq_id',
    'revision_code',
    'revision_number',
    'reason',
    'status',
    'created_by',
    'reviewed_by',
    'reviewed_at',
])]
class BoqRevision extends Model
{
    /** @use HasFactory<BoqRevisionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RevisionStatus::Open->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'revision_number' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisionItems(): HasMany
    {
        return $this->hasMany(BoqRevisionItem::class);
    }
}
