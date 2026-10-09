<?php

namespace App\Models;

use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentPlanException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\PaymentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'boq_id',
    'reference_code',
    'type',
    'planned_date',
    'amount',
    'status',
    'notes',
    'created_by',
])]
class PaymentPlan extends Model
{
    /** @use HasFactory<PaymentPlanFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => PaymentPlanType::Planned->value,
        'status' => PaymentPlanStatus::Planned->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentPlanType::class,
            'status' => PaymentPlanStatus::class,
            'planned_date' => 'date',
            'amount' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(BoqItem::class, 'payment_plan_items')
            ->withPivot('planned_amount')
            ->withTimestamps();
    }

    /**
     * The payment that currently settles this plan (VOID payments are ignored).
     */
    public function activePayment(): ?Payment
    {
        $this->loadMissing('payments');

        return $this->payments->first(fn (Payment $payment) => $payment->countsAsActual());
    }

    public function paidAmount(): float
    {
        return round((float) ($this->activePayment()?->amount ?? 0), 2);
    }

    public function markReadyToPay(): void
    {
        $this->status = PaymentPlanStatus::ReadyToPay;
        $this->type = PaymentPlanType::ReadyToPay;
        $this->save();
    }

    /**
     * @param  array{
     *     payment_code: string,
     *     payment_date: string|\DateTimeInterface,
     *     amount: float|int|string,
     *     paid_by: int,
     *     payment_method?: string|null,
     *     reference_number?: string|null,
     *     notes?: string|null
     * }  $attributes
     */
    public function recordPayment(array $attributes): Payment
    {
        if ($this->status === PaymentPlanStatus::Paid) {
            throw InvalidPaymentPlanException::alreadyPaid();
        }

        if ($this->status !== PaymentPlanStatus::ReadyToPay) {
            throw InvalidPaymentPlanException::notReadyToPay();
        }

        return DB::transaction(function () use ($attributes): Payment {
            $payment = $this->payments()->create([
                'payment_code' => $attributes['payment_code'],
                'payment_date' => $attributes['payment_date'],
                'amount' => $attributes['amount'],
                'payment_method' => $attributes['payment_method'] ?? null,
                'reference_number' => $attributes['reference_number'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'status' => PaymentStatus::Paid,
                'paid_by' => $attributes['paid_by'],
            ]);

            $this->status = PaymentPlanStatus::Paid;
            $this->save();

            return $payment;
        });
    }
}
