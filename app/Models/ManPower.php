<?php

namespace App\Models;

use Database\Factories\ManPowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'position',
    'daily_wage',
    'phone',
    'is_active',
    'notes',
    'created_by',
])]
class ManPower extends Model
{
    /** @use HasFactory<ManPowerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_wage' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projectManPowers(): HasMany
    {
        return $this->hasMany(ProjectManPower::class);
    }

    /**
     * Next sequential code (MP-001, MP-002, ...).
     */
    public static function nextCode(): string
    {
        $max = 0;

        foreach (self::query()->where('code', 'like', 'MP-%')->lockForUpdate()->pluck('code') as $code) {
            if (preg_match('/^MP-(\d+)$/i', (string) $code, $matches) === 1) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'MP-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
