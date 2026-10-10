<?php

namespace App\Models;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ProcurementRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'project_id',
    'request_number',
    'type',
    'request_date',
    'supplier',
    'notes',
    'estimated_total',
    'status',
    'created_by',
    'submitted_by',
    'submitted_at',
    'approved_by',
    'approved_at',
    'returned_by',
    'returned_at',
    'return_note',
    'paid_by',
    'paid_at',
    'payment_date',
    'paid_total',
    'invoice_number',
    'payment_proof_file',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class ProcurementRequest extends Model
{
    /** @use HasFactory<ProcurementRequestFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProcurementStatus::Draft->value,
        'estimated_total' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProcurementType::class,
            'status' => ProcurementStatus::class,
            'request_date' => 'date',
            'payment_date' => 'date',
            'estimated_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'returned_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class)->orderBy('id');
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(ProcurementAllocation::class, ProcurementRequestItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function returner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isPaid(): bool
    {
        return $this->status === ProcurementStatus::Paid;
    }

    public static function projectAcceptsRequests(Project $project): bool
    {
        return $project->status === ProjectStatus::Active;
    }

    public function assertProjectAcceptsRequests(): void
    {
        $this->loadMissing('project');

        if (! self::projectAcceptsRequests($this->project)) {
            throw BusinessRuleException::make('Permintaan barang hanya dapat diproses pada project berstatus ACTIVE.');
        }
    }

    public function assertStatus(ProcurementStatus $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw BusinessRuleException::make(
                "Permintaan hanya dapat {$action} dari status {$expected->label()}. Status saat ini: {$this->status->label()}."
            );
        }
    }

    public function assertEditable(): void
    {
        if (! $this->isEditable()) {
            throw BusinessRuleException::make(
                'Permintaan berstatus '.$this->status->label().' tidak dapat diubah.'
            );
        }

        $this->assertProjectAcceptsRequests();
    }

    public function recalculateEstimatedTotal(): void
    {
        $this->estimated_total = number_format((float) $this->items()->sum('total'), 2, '.', '');
        $this->save();
    }

    public function allocatedAmount(): float
    {
        $this->loadMissing('items.allocations');

        return round((float) $this->items->sum(fn (ProcurementRequestItem $item) => $item->allocatedAmount()), 2);
    }

    /**
     * Next sequential request number (PR-0001, PR-0002, ...).
     */
    public static function nextRequestNumber(): string
    {
        $max = 0;

        foreach (self::query()->where('request_number', 'like', 'PR-%')->lockForUpdate()->pluck('request_number') as $number) {
            if (preg_match('/^PR-(\d+)$/i', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'PR-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
