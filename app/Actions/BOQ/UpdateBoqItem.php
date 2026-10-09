<?php

namespace App\Actions\BOQ;

use App\Enums\BoqItemCategory;
use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BoqItem;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateBoqItem
{
    /**
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     quantity?: float|int|string,
     *     unit?: string,
     *     unit_price?: float|int|string,
     *     category?: string,
     *     sort_order?: int
     * }  $data
     */
    public function handle(User $actor, BoqItem $item, array $data): BoqItem
    {
        $item->loadMissing('cogs.boq');
        $this->assertBoqEditable($item);

        $validated = Validator::make($data, [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:50'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'category' => ['sometimes', 'required', Rule::enum(BoqItemCategory::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ], [
            'quantity.min' => 'Quantity tidak boleh kurang dari 0.',
            'unit_price.min' => 'Unit price tidak boleh kurang dari 0.',
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
