<?php

namespace App\Actions\BOQ;

use App\Enums\BoqItemCategory;
use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\Project;
use App\Models\User;
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
     *             code: string,
     *             name: string,
     *             description?: string|null,
     *             quantity: float|int|string,
     *             unit: string,
     *             unit_price: float|int|string,
     *             progress_percentage?: float|int|string,
     *             sort_order?: int
     *         }>
     *     }>
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data): Boq
    {
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
            'cogs.*.items.*.code' => ['required', 'string', 'max:50'],
            'cogs.*.items.*.name' => ['required', 'string', 'max:255'],
            'cogs.*.items.*.description' => ['nullable', 'string'],
            'cogs.*.items.*.quantity' => ['required', 'numeric', 'min:0'],
            'cogs.*.items.*.unit' => ['required', 'string', 'max:50'],
            'cogs.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'cogs.*.items.*.category' => ['nullable', Rule::enum(BoqItemCategory::class)],
            'cogs.*.items.*.progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cogs.*.items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'cogs.required' => 'BOQ harus memiliki minimal satu COGS.',
            'cogs.min' => 'BOQ harus memiliki minimal satu COGS.',
            'cogs.*.items.required' => 'Setiap COGS harus memiliki minimal satu Item.',
            'cogs.*.items.min' => 'Setiap COGS harus memiliki minimal satu Item.',
            'cogs.*.items.*.quantity.min' => 'Quantity tidak boleh kurang dari 0.',
            'cogs.*.items.*.unit_price.min' => 'Unit price tidak boleh kurang dari 0.',
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

                foreach ($cogsData['items'] as $itemIndex => $itemData) {
                    $this->assertPositiveContribution($itemData);

                    $itemRows[] = [
                        'code' => $itemData['code'],
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'],
                        'unit_price' => $itemData['unit_price'],
                        'category' => $itemData['category'] ?? BoqItemCategory::MainMaterial->value,
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
     * @param  array{quantity: float|int|string, unit_price: float|int|string}  $itemData
     */
    private function assertPositiveContribution(array $itemData): void
    {
        if ((float) $itemData['quantity'] * (float) $itemData['unit_price'] < 0) {
            throw BusinessRuleException::make('Total item tidak valid.');
        }
    }
}
