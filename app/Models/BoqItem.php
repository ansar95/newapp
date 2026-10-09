<?php

namespace App\Models;

use App\Enums\BoqItemCategory;
use App\Enums\ProgressStatus;
use App\Exceptions\InvalidProgressPercentageException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\BoqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'boq_cogs_id',
    'code',
    'name',
    'description',
    'quantity',
    'unit',
    'unit_price',
    'category',
    'progress_percentage',
    'sort_order',
])]
class BoqItem extends Model
{
    /** @use HasFactory<BoqItemFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'progress_percentage' => 0,
        'sort_order' => 0,
        'total_price' => 0,
        'category' => BoqItemCategory::MainMaterial->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'progress_percentage' => 'decimal:2',
            'sort_order' => 'integer',
            'category' => BoqItemCategory::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BoqItem $item): void {
            $item->assertValidProgressPercentage();
            $item->total_price = $item->calculateTotalPrice();
        });
    }

    protected function activeProjectRelation(): string
    {
        return 'cogs.boq.project';
    }

    public function cogs(): BelongsTo
    {
        return $this->belongsTo(BoqCogs::class, 'boq_cogs_id');
    }

    public function revisionItems(): HasMany
    {
        return $this->hasMany(BoqRevisionItem::class);
    }

    public function bmhpAllocations(): HasMany
    {
        return $this->hasMany(BmhpAllocation::class);
    }

    public function paymentPlans(): BelongsToMany
    {
        return $this->belongsToMany(PaymentPlan::class, 'payment_plan_items')
            ->withPivot('planned_amount')
            ->withTimestamps();
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function calculateTotalPrice(): string
    {
        $total = (float) $this->quantity * (float) $this->unit_price;

        return number_format($total, 2, '.', '');
    }

    public function weightAgainstCogs(): float
    {
        $this->loadMissing('cogs.items');

        $cogsTotal = $this->cogs?->totalAmount() ?? 0.0;

        if ($cogsTotal <= 0) {
            return 0.0;
        }

        return round(((float) $this->total_price / $cogsTotal) * 100, 2);
    }

    public function weightAgainstBoq(): float
    {
        $this->loadMissing('cogs.boq.cogs.items');

        $boqTotal = $this->cogs?->boq?->totalAmount() ?? 0.0;

        if ($boqTotal <= 0) {
            return 0.0;
        }

        return round(((float) $this->total_price / $boqTotal) * 100, 2);
    }

    public function progressStatus(): ProgressStatus
    {
        $progress = (float) $this->progress_percentage;

        if ($progress <= 0) {
            return ProgressStatus::NotStarted;
        }

        if ($progress >= 100) {
            return ProgressStatus::Completed;
        }

        return ProgressStatus::InProgress;
    }

    protected function assertValidProgressPercentage(): void
    {
        $progress = (float) $this->progress_percentage;

        if ($progress < 0 || $progress > 100) {
            throw InvalidProgressPercentageException::outOfRange($this->progress_percentage);
        }
    }
}
