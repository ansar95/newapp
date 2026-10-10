<?php

namespace App\Models;

use Database\Factories\ProcurementAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'procurement_request_item_id',
    'boq_item_id',
    'allocated_quantity',
    'allocated_amount',
    'notes',
])]
class ProcurementAllocation extends Model
{
    /** @use HasFactory<ProcurementAllocationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allocated_quantity' => 'decimal:4',
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id');
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }
}
