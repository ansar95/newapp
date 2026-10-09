<?php

namespace App\Actions\Bmhp;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchaseDetail;
use App\Models\BoqItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AllocateBmhpPurchaseDetail
{
    /**
     * Replace every allocation of a purchase detail with the given set.
     *
     * @param  list<array{
     *     boq_item_id: int|string,
     *     allocated_amount: float|int|string,
     *     allocated_quantity?: float|int|string|null,
     *     notes?: string|null
     * }>  $allocations
     */
    public function handle(User $actor, BmhpPurchaseDetail $detail, array $allocations): BmhpPurchaseDetail
    {
        $purchase = $detail->purchase;
        $purchase->assertEditable();

        $allocations = array_values(array_map(function (array $row): array {
            if (($row['allocated_quantity'] ?? null) === '') {
                $row['allocated_quantity'] = null;
            }

            return $row;
        }, $allocations));

        $validated = Validator::make(['allocations' => $allocations], [
            'allocations' => ['present', 'array'],
            'allocations.*.boq_item_id' => ['required', 'integer', 'distinct'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'gt:0'],
            'allocations.*.allocated_quantity' => ['nullable', 'numeric', 'gt:0'],
            'allocations.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'allocations.*.boq_item_id.distinct' => 'Item BOQ yang sama tidak boleh dipilih dua kali.',
            'allocations.*.allocated_amount.required' => 'Nilai alokasi wajib diisi.',
            'allocations.*.allocated_amount.gt' => 'Nilai alokasi harus lebih dari 0.',
            'allocations.*.allocated_quantity.gt' => 'Quantity alokasi harus lebih dari 0.',
        ])->validate()['allocations'];

        $rows = collect($validated);

        $this->assertTargetsBelongToProject($rows, (int) $purchase->project_id);

        $totalAllocatedCents = $rows->sum(fn (array $row) => BmhpAllocationMath::toCents($row['allocated_amount']));

        if ($totalAllocatedCents > BmhpAllocationMath::toCents($detail->total)) {
            throw BusinessRuleException::make(BmhpAllocationMath::EXCEEDS_MESSAGE);
        }

        $totalQuantityUnits = $rows->sum(fn (array $row) => BmhpAllocationMath::toQuantityUnits($row['allocated_quantity'] ?? 0));

        if ($totalQuantityUnits > BmhpAllocationMath::toQuantityUnits($detail->quantity)) {
            throw BusinessRuleException::make(BmhpAllocationMath::EXCEEDS_QUANTITY_MESSAGE);
        }

        return DB::transaction(function () use ($detail, $rows): BmhpPurchaseDetail {
            BmhpPurchaseDetail::query()->whereKey($detail->id)->lockForUpdate()->first();

            $detail->allocations()->delete();

            foreach ($rows as $row) {
                $detail->allocations()->create([
                    'boq_item_id' => (int) $row['boq_item_id'],
                    'allocated_amount' => $row['allocated_amount'],
                    'allocated_quantity' => $row['allocated_quantity'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $detail->fresh(['allocations.boqItem']);
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function assertTargetsBelongToProject(Collection $rows, int $projectId): void
    {
        $itemIds = $rows->pluck('boq_item_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($itemIds->isEmpty()) {
            return;
        }

        $validItemIds = BoqItem::query()
            ->whereIn('id', $itemIds)
            ->whereHas('cogs.boq', fn ($query) => $query
                ->where('project_id', $projectId)
                ->where('status', BoqStatus::Approved))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($itemIds->diff($validItemIds)->isNotEmpty()) {
            throw BusinessRuleException::make(
                'Alokasi hanya boleh ke Item BOQ dari BOQ APPROVED pada project yang sama.'
            );
        }
    }
}
