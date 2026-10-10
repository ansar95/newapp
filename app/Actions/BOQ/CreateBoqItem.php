<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\User;
use App\Support\BoqItemPriceInput;
use Illuminate\Support\Facades\Validator;

class CreateBoqItem
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     description?: string|null,
     *     quantity: float|int|string,
     *     unit: string,
     *     material_unit_price?: float|int|string,
     *     service_unit_price?: float|int|string|null,
     *     unit_price?: float|int|string,
     *     progress_percentage?: float|int|string,
     *     sort_order?: int
     * }  $data
     */
    public function handle(User $actor, BoqCogs $cogs, array $data): BoqItem
    {
        $cogs->loadMissing('boq');
        $this->assertBoqEditable($cogs);

        $validated = Validator::make(BoqItemPriceInput::normalize($data), [
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'material_unit_price' => ['required', 'numeric', 'min:0'],
            'service_unit_price' => ['nullable', 'numeric', 'min:0'],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'quantity.min' => 'Quantity tidak boleh kurang dari 0.',
            ...BoqItemPriceInput::messages(),
            'progress_percentage.min' => 'Progress harus berada antara 0 dan 100.',
            'progress_percentage.max' => 'Progress harus berada antara 0 dan 100.',
        ])->validate();

        if ($cogs->items()->where('code', $validated['code'])->exists()) {
            throw BusinessRuleException::make('Kode Item sudah digunakan pada COGS ini.');
        }

        return $cogs->items()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'material_unit_price' => $validated['material_unit_price'],
            'service_unit_price' => $validated['service_unit_price'] ?? 0,
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
