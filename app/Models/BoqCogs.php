<?php

namespace App\Models;

use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\BoqCogsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'boq_id',
    'code',
    'name',
    'description',
    'sort_order',
])]
class BoqCogs extends Model
{
    /** @use HasFactory<BoqCogsFactory> */
    use HasFactory, ResolvesWithActiveProject;

    protected $table = 'boqs_cogs';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    protected function activeProjectRelation(): string
    {
        return 'boq.project';
    }

    public function boq(): BelongsTo
    {
        return $this->belongsTo(Boq::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BoqItem::class, 'boq_cogs_id')->orderBy('sort_order');
    }

    /**
     * Next item code in this COGS (ITEM-001, ITEM-002, ...), continuing after the highest ITEM-n code.
     */
    public function nextItemCode(): string
    {
        return BoqItem::nextCodeAfter($this->items()->pluck('code'));
    }

    public function totalAmount(): float
    {
        $this->loadMissing('items');

        return round((float) $this->items->sum(fn (BoqItem $item): float => (float) $item->total_price), 2);
    }

    public function materialAmount(): float
    {
        $this->loadMissing('items');

        return round((float) $this->items->sum(fn (BoqItem $item): float => $item->materialTotal()), 2);
    }

    public function serviceAmount(): float
    {
        $this->loadMissing('items');

        return round((float) $this->items->sum(fn (BoqItem $item): float => $item->serviceTotal()), 2);
    }

    public function progressPercentage(): float
    {
        $this->loadMissing('items');

        $totalAmount = 0.0;
        $earnedAmount = 0.0;

        foreach ($this->items as $item) {
            $itemTotal = (float) $item->total_price;
            $totalAmount += $itemTotal;
            $earnedAmount += $itemTotal * ((float) $item->progress_percentage / 100);
        }

        if ($totalAmount <= 0) {
            return 0.0;
        }

        return round(($earnedAmount / $totalAmount) * 100, 2);
    }
}
