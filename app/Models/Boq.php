<?php

namespace App\Models;

use App\Enums\BoqStatus;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\BoqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'code',
    'revision_number',
    'status',
    'submitted_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'review_notes',
])]
class Boq extends Model
{
    /** @use HasFactory<BoqFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => BoqStatus::Draft->value,
        'revision_number' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BoqStatus::class,
            'revision_number' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function activeProjectRelation(): string
    {
        return 'project';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function cogs(): HasMany
    {
        return $this->hasMany(BoqCogs::class)->orderBy('sort_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BoqRevision::class);
    }

    public function paymentPlans(): HasMany
    {
        return $this->hasMany(PaymentPlan::class);
    }

    /**
     * Next COGS code for this BOQ (COGS-001, COGS-002, ...).
     */
    public function nextCogsCode(): string
    {
        $this->loadMissing('cogs');

        $max = 0;

        foreach ($this->cogs as $cogs) {
            if (preg_match('/^COGS-(\d+)$/i', (string) $cogs->code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'COGS-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function totalAmount(): float
    {
        $this->loadMissing('cogs.items');

        $total = 0.0;

        foreach ($this->cogs as $cogs) {
            $total += $cogs->totalAmount();
        }

        return round($total, 2);
    }

    public function materialAmount(): float
    {
        $this->loadMissing('cogs.items');

        return round((float) $this->cogs->sum(fn (BoqCogs $cogs): float => $cogs->materialAmount()), 2);
    }

    public function serviceAmount(): float
    {
        $this->loadMissing('cogs.items');

        return round((float) $this->cogs->sum(fn (BoqCogs $cogs): float => $cogs->serviceAmount()), 2);
    }

    public function progressPercentage(): float
    {
        $this->loadMissing('cogs.items');

        $totalAmount = 0.0;
        $earnedAmount = 0.0;

        foreach ($this->cogs as $cogs) {
            foreach ($cogs->items as $item) {
                $itemTotal = (float) $item->total_price;
                $totalAmount += $itemTotal;
                $earnedAmount += $itemTotal * ((float) $item->progress_percentage / 100);
            }
        }

        if ($totalAmount <= 0) {
            return 0.0;
        }

        return round(($earnedAmount / $totalAmount) * 100, 2);
    }
}
