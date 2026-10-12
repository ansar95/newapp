<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;
use App\Support\BoqItemPriceInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateBOQ
{
    /**
     * @param  array{
     *     code: string,
     *     revision_number?: int,
     *     cogs: list<array{
     *         code?: string|null,
     *         name: string,
     *         description?: string|null,
     *         sort_order?: int,
     *         items: list<array{
     *             code?: string|null,
     *             name: string,
     *             description?: string|null,
     *             notes?: string|null,
     *             quantity: float|int|string,
     *             unit: string,
     *             material_unit_price?: float|int|string,
     *             service_unit_price?: float|int|string|null,
     *             unit_price?: float|int|string,
     *             progress_percentage?: float|int|string,
     *             sort_order?: int
     *         }>
     *     }>
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data): Boq
    {
        $data = $this->normalizePrices($data);

        $validated = Validator::make($data, [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('boqs', 'code')->where(
                    fn ($query) => $query
                        ->where('project_id', $project->id)
                        ->where('revision_number', $data['revision_number'] ?? 1)
                ),
            ],
            'revision_number' => ['nullable', 'integer', 'min:1'],
            'cogs' => ['required', 'array', 'min:1'],
            'cogs.*.code' => ['nullable', 'string', 'max:50'],
            'cogs.*.name' => ['required', 'string', 'max:255'],
            'cogs.*.description' => ['nullable', 'string'],
            'cogs.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'cogs.*.items' => ['required', 'array', 'min:1'],
            'cogs.*.items.*.code' => ['nullable', 'string', 'max:50'],
            'cogs.*.items.*.name' => ['required', 'string', 'max:255'],
            'cogs.*.items.*.description' => ['nullable', 'string'],
            'cogs.*.items.*.notes' => ['nullable', 'string', 'max:255'],
            'cogs.*.items.*.quantity' => ['required', 'numeric', 'min:0'],
            'cogs.*.items.*.unit' => ['required', 'string', 'max:50'],
            'cogs.*.items.*.material_unit_price' => ['required', 'numeric', 'min:0'],
            'cogs.*.items.*.service_unit_price' => ['nullable', 'numeric', 'min:0'],
            'cogs.*.items.*.progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cogs.*.items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'cogs.required' => 'BOQ harus memiliki minimal satu COGS.',
            'cogs.min' => 'BOQ harus memiliki minimal satu COGS.',
            'cogs.*.items.required' => 'Setiap COGS harus memiliki minimal satu Item.',
            'cogs.*.items.min' => 'Setiap COGS harus memiliki minimal satu Item.',
            'cogs.*.items.*.notes.max' => 'Keterangan item maksimal 255 karakter.',
            'cogs.*.items.*.quantity.min' => 'Quantity tidak boleh kurang dari 0.',
            ...BoqItemPriceInput::messages('cogs.*.items.*.'),
        ])->validate();

        return DB::transaction(function () use ($project, $validated): Boq {
            $boq = Boq::query()->create([
                'project_id' => $project->id,
                'code' => $validated['code'],
                'revision_number' => $validated['revision_number'] ?? 1,
                'status' => BoqStatus::Draft,
            ]);

            foreach ($validated['cogs'] as $index => $cogsData) {
                $cogsCode = filled($cogsData['code'] ?? null)
                    ? $cogsData['code']
                    : 'COGS-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

                /** @var BoqCogs $cogs */
                $cogs = $boq->cogs()->create([
                    'code' => $cogsCode,
                    'name' => $cogsData['name'],
                    'description' => $cogsData['description'] ?? null,
                    'sort_order' => $cogsData['sort_order'] ?? $index + 1,
                ]);

                $itemRows = [];
                $usedCodes = collect($cogsData['items'])->pluck('code')->filter()->values()->all();

                foreach ($cogsData['items'] as $itemIndex => $itemData) {
                    $code = $itemData['code'] ?? null;

                    if (blank($code)) {
                        $code = BoqItem::nextCodeAfter($usedCodes);
                        $usedCodes[] = $code;
                    }

                    if (collect($itemRows)->contains('code', $code)) {
                        throw BusinessRuleException::make('Kode Item '.$code.' dipakai lebih dari sekali pada COGS '.$cogsData['name'].'.');
                    }

                    $itemRows[] = [
                        'code' => $code,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'],
                        'material_unit_price' => $itemData['material_unit_price'],
                        'service_unit_price' => $itemData['service_unit_price'] ?? 0,
                        'progress_percentage' => $itemData['progress_percentage'] ?? 0,
                        'sort_order' => $itemData['sort_order'] ?? $itemIndex + 1,
                    ];
                }

                $cogs->items()->createMany($itemRows);
            }

            $boq->refresh()->load('cogs.items');

            if ($boq->totalAmount() <= 0) {
                throw BusinessRuleException::make('BOQ tidak valid karena total BOQ harus lebih dari 0.');
            }

            return $boq;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePrices(array $data): array
    {
        if (! is_array($data['cogs'] ?? null)) {
            return $data;
        }

        foreach ($data['cogs'] as $cogsIndex => $cogsData) {
            if (! is_array($cogsData['items'] ?? null)) {
                continue;
            }

            foreach ($cogsData['items'] as $itemIndex => $itemData) {
                if (is_array($itemData)) {
                    $data['cogs'][$cogsIndex]['items'][$itemIndex] = BoqItemPriceInput::normalize($itemData);
                }
            }
        }

        return $data;
    }
}
