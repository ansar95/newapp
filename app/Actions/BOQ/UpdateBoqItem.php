<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BoqItem;
use App\Models\User;
use App\Support\BoqItemPriceInput;
use Illuminate\Support\Facades\Validator;

class UpdateBoqItem
{
    /**
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     notes?: string|null,
     *     level_3?: string|null,
     *     quantity?: float|int|string,
     *     unit?: string,
     *     material_unit_price?: float|int|string,
     *     service_unit_price?: float|int|string|null,
     *     unit_price?: float|int|string,
     *     sort_order?: int
     * }  $data
     */
    public function handle(User $actor, BoqItem $item, array $data): BoqItem
    {
        $item->loadMissing('cogs.boq');
        $this->assertBoqEditable($item);

        $validated = Validator::make(BoqItemPriceInput::normalize($data), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'level_3' => ['sometimes', 'nullable', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'material_unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'service_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ], [
            'notes.max' => 'Level 2 maksimal 255 karakter.',
            'level_3.max' => 'Level 3 maksimal 255 karakter.',
            'quantity.min' => 'Quantity tidak boleh kurang dari 0.',
            ...BoqItemPriceInput::messages(),
        ])->validate();

        $item->fill($validated);
        $item->save();

        return $item->refresh();
    }

    private function assertBoqEditable(BoqItem $item): void
    {
        if ($item->cogs?->boq?->status !== BoqStatus::Draft) {
            throw BusinessRuleException::make(
                'Item BOQ hanya dapat diubah ketika BOQ berstatus DRAFT. Perubahan pada BOQ APPROVED harus melalui mekanisme revision.'
            );
        }
    }
}
