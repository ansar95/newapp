<?php

namespace App\Models;

use App\Enums\ClientBillingStatus;
use App\Enums\ClientPaymentStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ClientBillingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'project_id',
    'project_term_id',
    'billing_number',
    'billing_date',
    'billing_amount',
    'invoice_number',
    'invoice_date',
    'due_date',
    'description',
    'attachment',
    'status',
    'created_by',
    'submitted_by',
    'submitted_at',
    'sent_by',
    'sent_at',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class ClientBilling extends Model
{
    /** @use HasFactory<ClientBillingFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ClientBillingStatus::Draft->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientBillingStatus::class,
            'billing_date' => 'date',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'billing_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'sent_at' => 'datetime',
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

    public function term(): BelongsTo
    {
        return $this->belongsTo(ProjectTerm::class, 'project_term_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ProjectDocument::class, 'reference')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public static function projectAcceptsBilling(Project $project): bool
    {
        return in_array($project->status, [ProjectStatus::Active, ProjectStatus::Completed], true);
    }

    public static function assertProjectAcceptsBilling(Project $project): void
    {
        if (! self::projectAcceptsBilling($project)) {
            throw BusinessRuleException::make('Penagihan client hanya dapat diproses pada project berstatus ACTIVE atau COMPLETED.');
        }
    }

    public function isCancelled(): bool
    {
        return $this->status === ClientBillingStatus::Cancelled;
    }

    public function receivedAmount(): float
    {
        $this->loadMissing('payments');

        return round((float) $this->payments
            ->filter(fn (ClientPayment $payment) => $payment->status === ClientPaymentStatus::Received)
            ->sum(fn (ClientPayment $payment) => (float) $payment->amount), 2);
    }

    public function outstandingAmount(): float
    {
        if (! $this->status->countsAsBilled()) {
            return 0.0;
        }

        return round(max(0, (float) $this->billing_amount - $this->receivedAmount()), 2);
    }

    /**
     * Workflow status, upgraded to PARTIALLY_PAID / PAID from the received amount.
     */
    public function displayStatus(): ClientBillingStatus
    {
        if ($this->status !== ClientBillingStatus::Sent) {
            return $this->status;
        }

        $receivedCents = (int) round($this->receivedAmount() * 100);

        return match (true) {
            $receivedCents >= (int) round((float) $this->billing_amount * 100) => ClientBillingStatus::Paid,
            $receivedCents > 0 => ClientBillingStatus::PartiallyPaid,
            default => ClientBillingStatus::Sent,
        };
    }

    /**
     * Next sequential billing number (BIL-0001, BIL-0002, ...).
     */
    public static function nextBillingNumber(): string
    {
        $max = 0;

        foreach (self::query()->where('billing_number', 'like', 'BIL-%')->lockForUpdate()->pluck('billing_number') as $number) {
            if (preg_match('/^BIL-(\d+)$/i', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'BIL-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
