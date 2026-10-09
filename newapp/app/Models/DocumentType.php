<?php

namespace App\Models;

use App\Enums\DocumentReferenceType;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'reference_type',
    'description',
    'is_active',
    'sort_order',
])]
class DocumentType extends Model
{
    public const BILLING = 'DOC-BILLING';

    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_type' => DocumentReferenceType::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function requiresBilling(): bool
    {
        return $this->reference_type === DocumentReferenceType::Billing;
    }
}
