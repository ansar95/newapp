<?php

namespace App\Models;

use App\Enums\ManPowerTransactionStatus;
use App\Enums\ManPowerTransactionType;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ProjectManPowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'man_power_id',
    'position',
    'daily_wage',
    'work_days',
    'start_date',
    'notes',
    'created_by',
])]
class ProjectManPower extends Model
{
    /** @use HasFactory<ProjectManPowerFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_wage' => 'decimal:2',
            'work_days' => 'integer',
            'start_date' => 'date',
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

    public function manPower(): BelongsTo
    {
        return $this->belongsTo(ManPower::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(ManPowerTransaction::class);
    }

    public function totalSalary(): float
    {
        return self::salaryFor($this->work_days, (float) $this->daily_wage);
    }

    public static function salaryFor(int $workDays, float $dailyWage): float
    {
        return round($workDays * $dailyWage, 2);
    }

    public function paidAmount(?ManPowerTransactionType $type = null): float
    {
        $transactions = $this->relationLoaded('transactions')
            ? $this->transactions
            : $this->transactions()->get();

        return round((float) $transactions
            ->where('status', ManPowerTransactionStatus::Paid)
            ->when($type !== null, fn ($items) => $items->where('type', $type))
            ->sum(fn (ManPowerTransaction $transaction) => (float) $transaction->amount), 2);
    }

    public function remainingSalary(): float
    {
        return round(max(0, $this->totalSalary() - $this->paidAmount()), 2);
    }
}
