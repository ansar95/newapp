<?php

namespace App\Models;

use App\Enums\BmhpPurchaseStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\BmhpPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'project_id',
    'purchase_number',
    'purchase_date',
    'supplier',
    'invoice_number',
    'receipt_file',
    'notes',
    'total_amount',
    'status',
    'created_by',
    'submitted_by',
    'submitted_at',
    'confirmed_by',
    'confirmed_at',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class BmhpPurchase extends Model
{
    /** @use HasFactory<BmhpPurchaseFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => BmhpPurchaseStatus::Draft->value,
        'total_amount' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BmhpPurchaseStatus::class,
            'purchase_date' => 'date',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
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

    public function details(): HasMany
    {
        return $this->hasMany(BmhpPurchaseDetail::class)->orderBy('id');
    }

    public function allocations(): HasManyThrough
    {
        return $this->hasManyThrough(BmhpAllocation::class, BmhpPurchaseDetail::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public static function projectAcceptsPurchases(Project $project): bool
    {
        return $project->status === ProjectStatus::Active;
    }

    public function assertProjectAcceptsPurchases(): void
    {
        $this->loadMissing('project');

        if (! self::projectAcceptsPurchases($this->project)) {
            throw BusinessRuleException::make('Pembelian BMHP hanya dapat diproses pada project berstatus ACTIVE.');
        }
    }

    public function assertEditable(): void
    {
        if (! $this->isEditable()) {
            throw BusinessRuleException::make(
                'Pembelian BMHP berstatus '.$this->status->value.' tidak dapat diubah. Gunakan pembatalan (CANCELLED) lalu buat transaksi baru.'
            );
        }

        $this->assertProjectAcceptsPurchases();
    }

    public function recalculateTotal(): void
    {
        $this->total_amount = number_format(
            (float) $this->details()->sum('total'),
            2,
            '.',
            ''
        );
        $this->save();
    }

    public function allocatedAmount(): float
    {
        $this->loadMissing('details.allocations');

        return round((float) $this->details->sum(fn (BmhpPurchaseDetail $detail) => $detail->allocatedAmount()), 2);
    }

    /**
     * Next sequential purchase number (PB-0001, PB-0002, ...).
     */
    public static function nextPurchaseNumber(): string
    {
        $max = 0;

        foreach (self::query()->where('purchase_number', 'like', 'PB-%')->lockForUpdate()->pluck('purchase_number') as $number) {
            if (preg_match('/^PB-(\d+)$/i', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'PB-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
