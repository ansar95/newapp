<?php

namespace App\Models;

use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentPlanException;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'payment_plan_id',
    'payment_code',
    'payment_date',
    'amount',
    'payment_method',
    'reference_number',
    'notes',
    'overrun_reason',
    'status',
    'paid_by',
    'voided_by',
    'voided_at',
    'void_reason',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => PaymentStatus::Paid->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $plan = PaymentPlan::query()->find($payment->payment_plan_id);

            if ($plan === null) {
                throw InvalidPaymentPlanException::notReadyToPay();
            }

            if ($plan->status === PaymentPlanStatus::Paid) {
                throw InvalidPaymentPlanException::alreadyPaid();
            }

            if ($plan->status !== PaymentPlanStatus::ReadyToPay) {
                throw InvalidPaymentPlanException::notReadyToPay();
            }
        });

        static::created(function (Payment $payment): void {
            $plan = $payment->paymentPlan;

            if ($plan !== null && $plan->status !== PaymentPlanStatus::Paid) {
                $plan->status = PaymentPlanStatus::Paid;
                $plan->save();
            }
        });
    }

    protected function activeProjectRelation(): string
    {
        return 'paymentPlan.boq.project';
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class)->orderBy('id');
    }

    public function countsAsActual(): bool
    {
        return $this->status->countsAsActual();
    }
}
