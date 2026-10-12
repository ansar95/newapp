<?php

namespace App\Models;

use App\Enums\BoqStatus;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'client_name',
    'contract_number',
    'contract_value',
    'description',
    'start_date',
    'target_end_date',
    'status',
    'created_by',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    public const CONTRACT_ENDING_ALERT_DAYS = 10;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProjectStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'contract_value' => 'decimal:2',
            'start_date' => 'date',
            'target_end_date' => 'date',
        ];
    }

    /**
     * Non-completed projects whose contract ends within the given days, including already overdue ones.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function contractEndingWithin(Builder $query, int $days = self::CONTRACT_ENDING_ALERT_DAYS): void
    {
        $query->where('status', '!=', ProjectStatus::Completed)
            ->whereNotNull('target_end_date')
            ->whereDate('target_end_date', '<=', today()->addDays($days));
    }

    /**
     * Days left until the contract end date. Negative when overdue, null when not tracked.
     */
    public function contractDaysRemaining(): ?int
    {
        if ($this->target_end_date === null || $this->status === ProjectStatus::Completed) {
            return null;
        }

        return (int) today()->diffInDays($this->target_end_date->copy()->startOfDay(), false);
    }

    public function isContractEndingSoon(): bool
    {
        $daysRemaining = $this->contractDaysRemaining();

        return $daysRemaining !== null
            && $daysRemaining >= 0
            && $daysRemaining <= self::CONTRACT_ENDING_ALERT_DAYS;
    }

    public function isContractOverdue(): bool
    {
        $daysRemaining = $this->contractDaysRemaining();

        return $daysRemaining !== null && $daysRemaining < 0;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function boqs(): HasMany
    {
        return $this->hasMany(Boq::class);
    }

    public function approvedBoqs(): HasMany
    {
        return $this->hasMany(Boq::class)->where('status', BoqStatus::Approved);
    }

    public function projectAssets(): HasMany
    {
        return $this->hasMany(ProjectAsset::class);
    }

    public function procurementRequests(): HasMany
    {
        return $this->hasMany(ProcurementRequest::class);
    }

    public function terms(): HasMany
    {
        return $this->hasMany(ProjectTerm::class)->orderBy('term_number');
    }

    public function clientBillings(): HasMany
    {
        return $this->hasMany(ClientBilling::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function manPowers(): HasMany
    {
        return $this->hasMany(ProjectManPower::class);
    }

    /**
     * Man power can only be assigned or re-scheduled while the project is running.
     */
    public function acceptsManPowerAssignment(): bool
    {
        return $this->status === ProjectStatus::Active;
    }

    /**
     * Kasbon and payouts may still be settled after the project is completed.
     */
    public function acceptsManPowerPayment(): bool
    {
        return in_array($this->status, [ProjectStatus::Active, ProjectStatus::Completed], true);
    }

    public function totalCost(): float
    {
        $total = 0.0;

        $this->loadMissing('approvedBoqs.cogs.items');

        foreach ($this->approvedBoqs as $boq) {
            $total += $boq->totalAmount();
        }

        return round($total, 2);
    }

    public function earnedValue(): float
    {
        $this->loadMissing('approvedBoqs.cogs.items');

        $earnedAmount = 0.0;

        foreach ($this->approvedBoqs as $boq) {
            foreach ($boq->cogs as $cogs) {
                foreach ($cogs->items as $item) {
                    $earnedAmount += (float) $item->total_price * ((float) $item->progress_percentage / 100);
                }
            }
        }

        return round($earnedAmount, 2);
    }

    public function progressPercentage(): float
    {
        $this->loadMissing('approvedBoqs.cogs.items');

        $totalAmount = $this->totalCost();

        if ($totalAmount <= 0) {
            return 0.0;
        }

        return round(($this->earnedValue() / $totalAmount) * 100, 2);
    }
}
