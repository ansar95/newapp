<?php

namespace App\Models;

use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ProcurementRequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'procurement_request_id',
    'item_name',
    'unit',
    'quantity',
    'unit_price',
    'paid_unit_price',
    'asset_category',
    'brand',
    'model',
    'rental_start',
    'rental_end',
    'notes',
])]
class ProcurementRequestItem extends Model
{
    /** @use HasFactory<ProcurementRequestItemFactory> */
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
            'paid_unit_price' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'rental_start' => 'date',
            'rental_end' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ProcurementRequestItem $item): void {
            $item->total = self::multiply($item->quantity, $item->unit_price);
            $item->paid_total = $item->paid_unit_price === null
                ? null
                : self::multiply($item->quantity, $item->paid_unit_price);
        });
    }

    protected function activeProjectRelation(): string
    {
        return 'request.project';
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ProcurementAllocation::class)->orderBy('id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * The value that counts as cost: the final paid total once paid, otherwise the estimate.
     */
    public function costAmount(): float
    {
        return round((float) ($this->paid_total ?? $this->total), 2);
    }

    public function allocatedAmount(): float
    {
        $this->loadMissing('allocations');

        return round((float) $this->allocations->sum(fn (ProcurementAllocation $allocation) => (float) $allocation->allocated_amount), 2);
    }

    public function unallocatedAmount(): float
    {
        return round(max(0, $this->costAmount() - $this->allocatedAmount()), 2);
    }

    private static function multiply(mixed $quantity, mixed $unitPrice): string
    {
        return number_format((float) $quantity * (float) $unitPrice, 2, '.', '');
    }
}
