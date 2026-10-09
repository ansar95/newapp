<?php

namespace App\Models;

use App\Enums\ClientPaymentStatus;
use App\Models\Concerns\ResolvesWithActiveProject;
use Database\Factories\ClientPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'client_billing_id',
    'payment_number',
    'payment_date',
    'amount',
    'bank_reference',
    'proof_file',
    'notes',
    'status',
    'created_by',
    'cancelled_by',
    'cancelled_at',
    'cancel_reason',
])]
class ClientPayment extends Model
{
    /** @use HasFactory<ClientPaymentFactory> */
    use HasFactory, ResolvesWithActiveProject;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ClientPaymentStatus::Received->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientPaymentStatus::class,
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'cancelled_at' => 'datetime',
        ];
    }

    protected function activeProjectRelation(): string
    {
        return 'billing.project';
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(ClientBilling::class, 'client_billing_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isReceived(): bool
    {
        return $this->status === ClientPaymentStatus::Received;
    }

    /**
     * Next sequential receipt number (RCV-0001, RCV-0002, ...).
     */
    public static function nextPaymentNumber(): string
    {
        $max = 0;

        foreach (self::query()->where('payment_number', 'like', 'RCV-%')->lockForUpdate()->pluck('payment_number') as $number) {
            if (preg_match('/^RCV-(\d+)$/i', (string) $number, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'RCV-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
