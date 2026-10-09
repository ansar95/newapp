<?php

namespace App\Models;

use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\BmhpPurchaseDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bmhp_purchase_id',
    'master_bmhp_id',
    'item_name',
    'unit',
    'quantity',
    'unit_price',
    'notes',
])]
class BmhpPurchaseDetail extends Model
{
    /** @use HasFactory<BmhpPurchaseDetailFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'total' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BmhpPurchaseDetail $detail): void {
            $detail->total = $detail->calculateTotal();
        });
    }

    protected function activeProjectRelation(): string
    {
        return 'purchase.project';
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(BmhpPurchase::class, 'bmhp_purchase_id');
    }

    public function masterBmhp(): BelongsTo
    {
        return $this->belongsTo(MasterBmhp::class)->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BmhpAllocation::class)->orderBy('id');
    }

    public function calculateTotal(): string
    {
        return number_format((float) $this->quantity * (float) $this->unit_price, 2, '.', '');
    }

    public function allocatedAmount(): float
    {
        $this->loadMissing('allocations');

        return round((float) $this->allocations->sum(fn (BmhpAllocation $allocation) => (float) $allocation->allocated_amount), 2);
    }

    public function unallocatedAmount(): float
    {
        return round(max(0, (float) $this->total - $this->allocatedAmount()), 2);
    }
}
