<?php

namespace App\Models;

use App\Enums\ManPowerTransactionStatus;
use App\Enums\ManPowerTransactionType;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ManPowerTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_man_power_id',
    'type',
    'transaction_date',
    'amount',
    'notes',
    'status',
    'created_by',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class ManPowerTransaction extends Model
{
    /** @use HasFactory<ManPowerTransactionFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ManPowerTransactionStatus::Paid->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ManPowerTransactionType::class,
            'status' => ManPowerTransactionStatus::class,
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function activeProjectRelation(): string
    {
        return 'projectManPower.project';
    }

    public function projectManPower(): BelongsTo
    {
        return $this->belongsTo(ProjectManPower::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isPaid(): bool
    {
        return $this->status === ManPowerTransactionStatus::Paid;
    }
}
