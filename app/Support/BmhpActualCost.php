<?php

namespace App\Support;

use App\Enums\BmhpPurchaseStatus;
use App\Models\BmhpAllocation;
use App\Models\BmhpPurchase;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\Project;

/**
 * Read-only actual cost calculator. Only CONFIRMED purchases count, and actual is always SUM(allocated_amount).
 */
class BmhpActualCost
{
    /**
     * @param  array<int, float>  $itemActuals  keyed by boq_item_id
     */
    private function __construct(
        public readonly Project $project,
        private readonly array $itemActuals,
        public readonly float $confirmedPurchaseTotal,
        public readonly int $confirmedPurchaseCount,
    ) {}

    public static function forProject(Project $project): self
    {
        $itemActuals = BmhpAllocation::query()
            ->join('bmhp_purchase_details', 'bmhp_purchase_details.id', '=', 'bmhp_allocations.bmhp_purchase_detail_id')
            ->join('bmhp_purchases', 'bmhp_purchases.id', '=', 'bmhp_purchase_details.bmhp_purchase_id')
            ->where('bmhp_purchases.project_id', $project->id)
            ->where('bmhp_purchases.status', BmhpPurchaseStatus::Confirmed->value)
            ->groupBy('bmhp_allocations.boq_item_id')
            ->selectRaw('bmhp_allocations.boq_item_id, SUM(bmhp_allocations.allocated_amount) as actual_amount')
            ->pluck('actual_amount', 'boq_item_id')
            ->mapWithKeys(fn ($amount, $itemId) => [(int) $itemId => round((float) $amount, 2)])
            ->all();

        $confirmedPurchases = BmhpPurchase::query()
            ->where('project_id', $project->id)
            ->where('status', BmhpPurchaseStatus::Confirmed);

        return new self(
            $project,
            $itemActuals,
            round((float) (clone $confirmedPurchases)->sum('total_amount'), 2),
            (clone $confirmedPurchases)->count(),
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
     * Confirmed purchase value that has not been allocated to any BOQ item yet.
     */
    public function unallocatedAmount(): float
    {
        return round(max(0, $this->confirmedPurchaseTotal - $this->projectActual()), 2);
    }

    public static function variance(float $baseline, float $actual): float
    {
        return round($baseline - $actual, 2);
    }

    public static function percentage(float $baseline, float $actual): float
    {
        if ($baseline <= 0) {
            return 0.0;
        }

        return round(($actual / $baseline) * 100, 2);
    }
}
