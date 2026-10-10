<?php

namespace App\Support;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\ProcurementAllocation;
use App\Models\ProcurementRequest;
use App\Models\Project;

/**
 * Read-only actual cost of one procurement type (BMHP or rental). Only PAID requests count, and actual is always SUM(allocated_amount).
 */
class ProcurementActualCost
{
    /**
     * @param  array<int, float>  $itemActuals  keyed by boq_item_id
     */
    private function __construct(
        public readonly Project $project,
        public readonly ProcurementType $type,
        private readonly array $itemActuals,
        public readonly float $paidTotal,
        public readonly int $paidCount,
    ) {}

    public static function forProject(Project $project, ProcurementType $type): self
    {
        $itemActuals = ProcurementAllocation::query()
            ->join('procurement_request_items', 'procurement_request_items.id', '=', 'procurement_allocations.procurement_request_item_id')
            ->join('procurement_requests', 'procurement_requests.id', '=', 'procurement_request_items.procurement_request_id')
            ->where('procurement_requests.project_id', $project->id)
            ->where('procurement_requests.type', $type->value)
            ->where('procurement_requests.status', ProcurementStatus::Paid->value)
            ->groupBy('procurement_allocations.boq_item_id')
            ->selectRaw('procurement_allocations.boq_item_id, SUM(procurement_allocations.allocated_amount) as actual_amount')
            ->pluck('actual_amount', 'boq_item_id')
            ->mapWithKeys(fn ($amount, $itemId) => [(int) $itemId => round((float) $amount, 2)])
            ->all();

        $paidRequests = ProcurementRequest::query()
            ->where('project_id', $project->id)
            ->where('type', $type)
            ->where('status', ProcurementStatus::Paid);

        return new self(
            $project,
            $type,
            $itemActuals,
            round((float) (clone $paidRequests)->sum('paid_total'), 2),
            (clone $paidRequests)->count(),
        );
    }

    public function forItem(BoqItem|int $item): float
    {
        $itemId = $item instanceof BoqItem ? $item->id : $item;

        return $this->itemActuals[$itemId] ?? 0.0;
    }

    public function forCogs(BoqCogs $cogs): float
    {
        $cogs->loadMissing('items');

        return round((float) $cogs->items->sum(fn (BoqItem $item) => $this->forItem($item)), 2);
    }

    public function forBoq(Boq $boq): float
    {
        $boq->loadMissing('cogs.items');

        return round((float) $boq->cogs->sum(fn (BoqCogs $cogs) => $this->forCogs($cogs)), 2);
    }

    public function projectActual(): float
    {
        return round(array_sum($this->itemActuals), 2);
    }

    /**
     * Paid value that has not been allocated to any BOQ item yet.
     */
    public function unallocatedAmount(): float
    {
        return round(max(0, $this->paidTotal - $this->projectActual()), 2);
    }
}
