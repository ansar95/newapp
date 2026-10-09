<?php

namespace App\Actions\BOQ;

use App\Enums\BoqItemCategory;
use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateBoqItem
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description?: string|null,
     *     quantity: float|int|string,
     *     unit: string,
     *     unit_price: float|int|string,
     *     category?: string,
     *     progress_percentage?: float|int|string,
     *     sort_order?: int
     * }  $data
     */
    public function handle(User $actor, BoqCogs $cogs, array $data): BoqItem
    {
        $cogs->loadMissing('boq');
        $this->assertBoqEditable($cogs);

        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'category' => ['nullable', Rule::enum(BoqItemCategory::class)],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'quantity.min' => 'Quantity tidak boleh kurang dari 0.',
            'unit_price.min' => 'Unit price tidak boleh kurang dari 0.',
            'progress_percentage.min' => 'Progress harus berada antara 0 dan 100.',
            'progress_percentage.max' => 'Progress harus berada antara 0 dan 100.',
            'category' => 'Kategori item harus Consumable atau Material Utama.',
        ])->validate();

        if ($cogs->items()->where('code', $validated['code'])->exists()) {
            throw BusinessRuleException::make('Kode Item sudah digunakan pada COGS ini.');
        }

        $category = isset($validated['category'])
            ? ($validated['category'] instanceof BoqItemCategory
                ? $validated['category']
                : BoqItemCategory::from($validated['category']))
            : BoqItemCategory::MainMaterial;

        return $cogs->items()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'],
            'category' => $category,
            'progress_percentage' => $validated['progress_percentage'] ?? 0,
            'sort_order' => $validated['sort_order'] ?? (($cogs->items()->max('sort_order') ?? 0) + 1),
        ]);
    }

    private function assertBoqEditable(BoqCogs $cogs): void
    {
        if ($cogs->boq?->status !== BoqStatus::Draft) {
            throw BusinessRuleException::make(
                'Item hanya dapat ditambahkan ketika BOQ berstatus DRAFT. BOQ yang sudah APPROVED tidak boleh diedit secara bebas.'
            );
        }
    }
}
