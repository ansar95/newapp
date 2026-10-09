<?php

namespace App\Models;

use App\Enums\RevisionItemStatus;
use Database\Factories\BoqRevisionItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'boq_revision_id',
    'boq_item_id',
    'reason',
    'status',
])]
class BoqRevisionItem extends Model
{
    /** @use HasFactory<BoqRevisionItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RevisionItemStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RevisionItemStatus::class,
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(BoqRevision::class, 'boq_revision_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class, 'boq_item_id');
    }
}
