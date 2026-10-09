<?php

namespace App\Models;

use App\Enums\MasterBmhpStatus;
use Database\Factories\MasterBmhpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'category',
    'unit',
    'description',
    'status',
    'created_by',
])]
class MasterBmhp extends Model
{
    /** @use HasFactory<MasterBmhpFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => MasterBmhpStatus::Active->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MasterBmhpStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function purchaseDetails(): HasMany
    {
        return $this->hasMany(BmhpPurchaseDetail::class);
    }

    public function isActive(): bool
    {
        return $this->status === MasterBmhpStatus::Active;
    }

    public function isUsedInPurchases(): bool
    {
        return $this->purchaseDetails()->exists();
    }

    /**
     * Next sequential master code (BMHP-0001, BMHP-0002, ...).
     */
    public static function nextCode(): string
    {
        $max = 0;

        foreach (self::withTrashed()->where('code', 'like', 'BMHP-%')->pluck('code') as $code) {
            if (preg_match('/^BMHP-(\d+)$/i', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'BMHP-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<string>
     */
    public static function categoryOptions(): array
    {
        return [
            'Material',
            'Consumable',
            'Alat Bantu',
            'Bahan Kimia',
            'Lainnya',
        ];
    }
}
