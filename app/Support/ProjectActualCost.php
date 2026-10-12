<?php

namespace App\Support;

use App\Enums\ManPowerTransactionStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementType;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\ManPowerTransaction;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Project;

/**
 * Read-only actual cost of a project: paid BMHP allocations + paid rental allocations + PAID payment allocations
 * + paid man power kasbon / payouts. Man power is a project-level cost and is never attributed to BOQ items.
 */
class ProjectActualCost
{
    /**
     * @param  array<int, float>  $paymentItemActuals  keyed by boq_item_id
     */
    private function __construct(
        public readonly Project $project,
        public readonly ProcurementActualCost $bmhp,
        public readonly ProcurementActualCost $rental,
        private readonly array $paymentItemActuals,
        public readonly float $paidPaymentTotal,
        private readonly float $manPowerTotal,
    ) {}

    public static function forProject(Project $project): self
    {
        $paymentItemActuals = PaymentAllocation::query()
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->join('payment_plans', 'payment_plans.id', '=', 'payments.payment_plan_id')
            ->join('boqs', 'boqs.id', '=', 'payment_plans.boq_id')
            ->where('boqs.project_id', $project->id)
            ->where('payments.status', PaymentStatus::Paid->value)
            ->groupBy('payment_allocations.boq_item_id')
            ->selectRaw('payment_allocations.boq_item_id, SUM(payment_allocations.allocated_amount) as actual_amount')
            ->pluck('actual_amount', 'boq_item_id')
            ->mapWithKeys(fn ($amount, $itemId) => [(int) $itemId => round((float) $amount, 2)])
            ->all();

        $paidPaymentTotal = (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereHas('paymentPlan.boq', fn ($query) => $query->where('project_id', $project->id))
            ->sum('amount');

        $manPowerTotal = (float) ManPowerTransaction::query()
            ->where('status', ManPowerTransactionStatus::Paid)
            ->whereHas('projectManPower', fn ($query) => $query->where('project_id', $project->id))
            ->sum('amount');

        return new self(
            $project,
            ProcurementActualCost::forProject($project, ProcurementType::Bmhp),
            ProcurementActualCost::forProject($project, ProcurementType::Rental),
            $paymentItemActuals,
            round($paidPaymentTotal, 2),
            round($manPowerTotal, 2),
        );
    }

    public function bmhpForItem(BoqItem|int $item): float
    {
        return $this->bmhp->forItem($item);
    }

    public function rentalForItem(BoqItem|int $item): float
    {
        return $this->rental->forItem($item);
    }

    public function paymentForItem(BoqItem|int $item): float
    {
        $itemId = $item instanceof BoqItem ? $item->id : $item;

        return $this->paymentItemActuals[$itemId] ?? 0.0;
    }

    public function forItem(BoqItem|int $item): float
    {
        return round($this->bmhpForItem($item) + $this->rentalForItem($item) + $this->paymentForItem($item), 2);
    }

    public function bmhpForCogs(BoqCogs $cogs): float
    {
        return $this->bmhp->forCogs($cogs);
    }

    public function rentalForCogs(BoqCogs $cogs): float
    {
        return $this->rental->forCogs($cogs);
    }

    public function paymentForCogs(BoqCogs $cogs): float
    {
        $cogs->loadMissing('items');

        return round((float) $cogs->items->sum(fn (BoqItem $item) => $this->paymentForItem($item)), 2);
    }

    public function forCogs(BoqCogs $cogs): float
    {
        return round($this->bmhpForCogs($cogs) + $this->rentalForCogs($cogs) + $this->paymentForCogs($cogs), 2);
    }

    public function forBoq(Boq $boq): float
    {
        $boq->loadMissing('cogs.items');

        return round((float) $boq->cogs->sum(fn (BoqCogs $cogs) => $this->forCogs($cogs)), 2);
    }

    public function bmhpActual(): float
    {
        return $this->bmhp->projectActual();
    }

    public function rentalActual(): float
    {
        return $this->rental->projectActual();
    }

    public function paymentActual(): float
    {
        return round(array_sum($this->paymentItemActuals), 2);
    }

    public function manPowerActual(): float
    {
        return $this->manPowerTotal;
    }

    public function projectActual(): float
    {
        return round($this->bmhpActual() + $this->rentalActual() + $this->paymentActual() + $this->manPowerActual(), 2);
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
