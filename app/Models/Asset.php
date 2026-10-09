<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'category',
    'brand',
    'model',
    'unit',
    'source_type',
    'status',
    'condition',
    'purchase_year',
    'description',
    'request_number',
    'request_date',
    'request_letter_file',
    'request_description',
    'requested_by',
    'request_status',
    'created_by',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => AssetStatus::Available->value,
        'condition' => AssetCondition::Good->value,
        'unit' => 'Unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_type' => AssetSourceType::class,
            'status' => AssetStatus::class,
            'condition' => AssetCondition::class,
            'request_status' => AssetRequestStatus::class,
            'request_date' => 'date',
            'purchase_year' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function projectAssets(): HasMany
    {
        return $this->hasMany(ProjectAsset::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(ProjectAsset::class)
            ->where('status', ProjectAssetStatus::InUse->value)
            ->latestOfMany('assigned_at');
    }

    public function isUsedInProjects(): bool
    {
        return $this->projectAssets()->exists();
    }

    public function hasActiveAssignment(): bool
    {
        return $this->projectAssets()
            ->where('status', ProjectAssetStatus::InUse)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public static function categoryOptions(): array
    {
        return [
            'IT Equipment',
            'Office Equipment',
            'Vehicle',
            'Tools',
            'Machinery',
            'Other',
        ];
    }
}
