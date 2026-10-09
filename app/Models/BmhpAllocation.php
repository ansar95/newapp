<?php

namespace App\Models;

use Database\Factories\BmhpAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bmhp_purchase_detail_id',
    'boq_item_id',
    'allocated_quantity',
    'allocated_amount',
    'notes',
])]
class BmhpAllocation extends Model
{
    /** @use HasFactory<BmhpAllocationFactory> */
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

    public function purchaseDetail(): BelongsTo
    {
        return $this->belongsTo(BmhpPurchaseDetail::class, 'bmhp_purchase_detail_id');
    }

    public function boqItem(): BelongsTo
    {
        return $this->belongsTo(BoqItem::class);
    }
}
