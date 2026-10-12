<?php

namespace App\Support;

use App\Enums\ClientPaymentStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Enums\ProgressStatus;
use App\Enums\ProjectAssetStatus;
use App\Enums\ProjectReportSection;
use App\Enums\ProjectVarianceStatus;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectDocument;
use App\Models\ProjectTerm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only report figures of one project. Snapshot sections (summary, progress, cost, documents) show the current
 * position; transaction sections (procurement, payment, billing, assets) are limited to the optional period.
 */
class ProjectReport
{
    private ?ProjectActualCost $actualCost = null;

    private ?ProjectBillingSummary $billingSummary = null;

    private function __construct(
        public readonly Project $project,
        public readonly User $viewer,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
        public readonly ?ProcurementType $procurementType,
        public readonly ?ProcurementStatus $procurementStatus,
    ) {}

    public static function for(
        Project $project,
        User $viewer,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?ProcurementType $procurementType = null,
        ?ProcurementStatus $procurementStatus = null,
    ): self {
        $project->loadMissing('approvedBoqs.cogs.items');

        return new self($project, $viewer, $from?->startOfDay(), $to?->endOfDay(), $procurementType, $procurementStatus);
    }

    public function canSee(ProjectReportSection $section): bool
    {
        return $section->isVisibleTo($this->viewer);
    }

    public function hasPeriod(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    public function periodLabel(): string
    {
        if (! $this->hasPeriod()) {
            return 'Semua periode';
        }

        return ($this->from ? date_id($this->from) : 'awal').' s/d '.($this->to ? date_id($this->to) : 'sekarang');
    }

    public function actualCost(): ProjectActualCost
    {
        return $this->actualCost ??= ProjectActualCost::forProject($this->project);
    }

    public function billingSummary(): ProjectBillingSummary
    {
        return $this->billingSummary ??= ProjectBillingSummary::forProject($this->project);
    }

    /**
     * Current position of the project. Blocks the viewer may not see are null.
     *
     * @return array{
     *     contract_value: float,
     *     progress: array{boq_value: float, progress: float, earned_value: float}|null,
     *     cost: array{bmhp: float, rental: float, payment: float, actual: float, percentage: float, variance: float, status: ?ProjectVarianceStatus}|null,
     *     payment: array{planned: float, paid: float}|null,
     *     procurement: array{paid: array<string, float>, waiting: int}|null,
     *     billing: array{billed: float, received: float, outstanding: float, billed_percentage: float}|null,
     *     assets: array{in_use: int}|null,
     *     documents: array{total: int, waiting: int, approved: int}|null
     * }
     */
    public function summary(): array
    {
        $boqValue = $this->project->totalCost();

        return [
            'contract_value' => (float) ($this->project->contract_value ?? 0),
            'progress' => $this->canSee(ProjectReportSection::Progress) ? [
                'boq_value' => $boqValue,
                'progress' => $this->project->progressPercentage(),
                'earned_value' => $this->project->earnedValue(),
            ] : null,
            'cost' => $this->canSee(ProjectReportSection::Cost) ? $this->costSummary($boqValue) : null,
            'payment' => $this->canSee(ProjectReportSection::Payment) ? $this->paymentPlanTotals() : null,
            'procurement' => $this->canSee(ProjectReportSection::Procurement) ? $this->procurementTotals() : null,
            'billing' => $this->canSee(ProjectReportSection::Billing) ? [
                'billed' => $this->billingSummary()->totalBilled(),
                'received' => $this->billingSummary()->totalReceived(),
                'outstanding' => $this->billingSummary()->outstanding(),
                'billed_percentage' => $this->billingSummary()->billedPercentage(),
            ] : null,
            'assets' => $this->canSee(ProjectReportSection::Assets) ? [
                'in_use' => $this->project->projectAssets()->where('status', ProjectAssetStatus::InUse)->count(),
            ] : null,
            'documents' => $this->canSee(ProjectReportSection::Documents) ? $this->documentTotals() : null,
        ];
    }

    /**
     * @return array{
     *     boqs: Collection<int, Boq>,
     *     summary: array{boq_value: float, earned_value: float, progress: float, items: int, not_started: int, in_progress: int, completed: int}
     * }
     */
    public function progress(): array
    {
        $items = $this->boqItems();

        return [
            'boqs' => $this->project->approvedBoqs,
            'summary' => [
                'boq_value' => $this->project->totalCost(),
                'earned_value' => $this->project->earnedValue(),
                'progress' => $this->project->progressPercentage(),
                'items' => $items->count(),
                'not_started' => $items->filter(fn (BoqItem $item) => $item->progressStatus() === ProgressStatus::NotStarted)->count(),
                'in_progress' => $items->filter(fn (BoqItem $item) => $item->progressStatus() === ProgressStatus::InProgress)->count(),
                'completed' => $items->filter(fn (BoqItem $item) => $item->progressStatus() === ProgressStatus::Completed)->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     requests: Collection<int, ProcurementRequest>,
     *     by_type: array<string, array{type: ProcurementType, count: int, estimated: float, paid: float, allocated: float}>,
     *     by_status: array<string, int>
     * }
     */
    public function procurement(): array
    {
        $requests = $this->project->procurementRequests()
            ->with(['items.allocations', 'creator'])
            ->when($this->procurementType, fn (Builder $query) => $query->where('type', $this->procurementType))
            ->when($this->procurementStatus, fn (Builder $query) => $query->where('status', $this->procurementStatus))
            ->when($this->from, fn (Builder $query) => $query->whereDate('request_date', '>=', $this->from))
            ->when($this->to, fn (Builder $query) => $query->whereDate('request_date', '<=', $this->to))
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        $active = $requests->where('status', '!=', ProcurementStatus::Cancelled);

        return [
            'requests' => $requests,
            'by_type' => collect(ProcurementType::cases())->mapWithKeys(function (ProcurementType $requestType) use ($active) {
                $ofType = $active->where('type', $requestType);

                return [$requestType->value => [
                    'type' => $requestType,
                    'count' => $ofType->count(),
                    'estimated' => round((float) $ofType->sum(fn (ProcurementRequest $request) => (float) $request->estimated_total), 2),
                    'paid' => round((float) $ofType->where('status', ProcurementStatus::Paid)->sum(fn (ProcurementRequest $request) => (float) $request->paid_total), 2),
                    'allocated' => $requestType->isAllocatable()
                        ? round((float) $ofType->where('status', ProcurementStatus::Paid)->sum(fn (ProcurementRequest $request) => $request->allocatedAmount()), 2)
                        : 0.0,
                ]];
            })->all(),
            'by_status' => collect(ProcurementStatus::cases())->mapWithKeys(fn (ProcurementStatus $requestStatus) => [
                $requestStatus->value => $requests->where('status', $requestStatus)->count(),
            ])->all(),
        ];
    }

    /**
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     summary: array{boq_value: float, bmhp: float, rental: float, payment: float, man_power: float, actual: float, variance: float, percentage: float, over_budget: int}
     * }
     */
    public function cost(): array
    {
        $actualCost = $this->actualCost();

        $rows = $this->boqItems()->map(function (BoqItem $item) use ($actualCost) {
            $boqValue = (float) $item->total_price;
            $actual = $actualCost->forItem($item);

            return [
                'item' => $item,
                'boq_code' => $item->cogs?->boq?->code,
                'cogs' => $item->cogs,
                'boq_value' => $boqValue,
                'bmhp' => $actualCost->bmhpForItem($item),
                'rental' => $actualCost->rentalForItem($item),
                'payment' => $actualCost->paymentForItem($item),
                'actual' => $actual,
                'variance' => ProjectActualCost::variance($boqValue, $actual),
                'percentage' => ProjectActualCost::percentage($boqValue, $actual),
                'status' => ProjectVarianceStatus::fromAmounts($boqValue, $actual),
                'progress' => (float) $item->progress_percentage,
            ];
        });

        $boqValue = round((float) $rows->sum('boq_value'), 2);
        $manPower = $actualCost->manPowerActual();
        $actual = round((float) $rows->sum('actual') + $manPower, 2);

        return [
            'rows' => $rows,
            'summary' => [
                'boq_value' => $boqValue,
                'bmhp' => round((float) $rows->sum('bmhp'), 2),
                'rental' => round((float) $rows->sum('rental'), 2),
                'payment' => round((float) $rows->sum('payment'), 2),
                'man_power' => $manPower,
                'actual' => $actual,
                'variance' => ProjectActualCost::variance($boqValue, $actual),
                'percentage' => ProjectActualCost::percentage($boqValue, $actual),
                'over_budget' => $rows->where('status', ProjectVarianceStatus::OverBudget)->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     plans: Collection<int, PaymentPlan>,
     *     payments: Collection<int, Payment>,
     *     summary: array{plans: int, planned: float, paid_all_time: float, paid_in_period: float, payments: int, voided: int}
     * }
     */
    public function payment(): array
    {
        $plans = $this->paymentPlans();

        $payments = $plans
            ->flatMap(fn (PaymentPlan $plan) => $plan->payments->each(fn (Payment $payment) => $payment->setRelation('paymentPlan', $plan)))
            ->filter(fn (Payment $payment) => $this->inPeriod($payment->payment_date))
            ->sortBy([['payment_date', 'asc'], ['id', 'asc']])
            ->values();

        $paid = $payments->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Paid);

        return [
            'plans' => $plans,
            'payments' => $payments,
            'summary' => [
                'plans' => $plans->count(),
                'planned' => round((float) $plans->sum(fn (PaymentPlan $plan) => (float) $plan->amount), 2),
                'paid_all_time' => round((float) $plans->sum(fn (PaymentPlan $plan) => $plan->paidAmount()), 2),
                'paid_in_period' => round((float) $paid->sum(fn (Payment $payment) => (float) $payment->amount), 2),
                'payments' => $paid->count(),
                'voided' => $payments->where('status', PaymentStatus::Void)->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     terms: Collection<int, ProjectTerm>,
     *     billings: Collection<int, ClientBilling>,
     *     clientPayments: Collection<int, ClientPayment>,
     *     billingSummary: ProjectBillingSummary,
     *     summary: array{billed_in_period: float, received_in_period: float, overdue: float, overdue_count: int}
     * }
     */
    public function billing(): array
    {
        $billingSummary = $this->billingSummary();

        $billings = $billingSummary->billings
            ->filter(fn (ClientBilling $billing) => $this->inPeriod($billing->billing_date))
            ->values();

        $clientPayments = $billingSummary->billings
            ->flatMap(fn (ClientBilling $billing) => $billing->payments->each(fn (ClientPayment $payment) => $payment->setRelation('billing', $billing)))
            ->filter(fn (ClientPayment $payment) => $this->inPeriod($payment->payment_date))
            ->sortBy([['payment_date', 'asc'], ['id', 'asc']])
            ->values();

        $overdue = $billingSummary->sentBillings()
            ->filter(fn (ClientBilling $billing) => $billing->due_date !== null
                && $billing->due_date->lt(today())
                && $billing->outstandingAmount() > 0);

        return [
            'terms' => $billingSummary->terms,
            'billings' => $billings,
            'clientPayments' => $clientPayments,
            'billingSummary' => $billingSummary,
            'summary' => [
                'billed_in_period' => round((float) $billings
                    ->filter(fn (ClientBilling $billing) => $billing->status->countsAsBilled())
                    ->sum(fn (ClientBilling $billing) => (float) $billing->billing_amount), 2),
                'received_in_period' => round((float) $clientPayments
                    ->filter(fn (ClientPayment $payment) => $payment->status === ClientPaymentStatus::Received)
                    ->sum(fn (ClientPayment $payment) => (float) $payment->amount), 2),
                'overdue' => round((float) $overdue->sum(fn (ClientBilling $billing) => $billing->outstandingAmount()), 2),
                'overdue_count' => $overdue->count(),
            ],
        ];
    }

    /**
     * Assignments active at any moment inside the period.
     *
     * @return array{
     *     assignments: Collection<int, ProjectAsset>,
     *     summary: array{total: int, in_use: int, released: int, purchased: int, purchased_value: float}
     * }
     */
    public function assets(): array
    {
        $assignments = $this->project->projectAssets()
            ->with(['asset.procurementRequestItem.request', 'assignedBy', 'releasedBy'])
            ->when($this->to, fn (Builder $query) => $query->where('assigned_at', '<=', $this->to))
            ->when($this->from, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereNull('released_at')
                ->orWhere('released_at', '>=', $this->from)))
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        $purchasedItems = ProcurementRequestItem::query()
            ->whereHas('request', fn (Builder $query) => $query
                ->where('project_id', $this->project->id)
                ->where('type', ProcurementType::Asset)
                ->where('status', ProcurementStatus::Paid)
                ->when($this->from, fn (Builder $inner) => $inner->whereDate('payment_date', '>=', $this->from))
                ->when($this->to, fn (Builder $inner) => $inner->whereDate('payment_date', '<=', $this->to)))
            ->get();

        return [
            'assignments' => $assignments,
            'summary' => [
                'total' => $assignments->count(),
                'in_use' => $assignments->where('status', ProjectAssetStatus::InUse)->count(),
                'released' => $assignments->where('status', ProjectAssetStatus::Released)->count(),
                'purchased' => (int) $purchasedItems->sum(fn (ProcurementRequestItem $item) => (int) $item->quantity),
                'purchased_value' => round((float) $purchasedItems->sum(fn (ProcurementRequestItem $item) => $item->costAmount()), 2),
            ],
        ];
    }

    public function documents(): ProjectDocumentMonitor
    {
        return ProjectDocumentMonitor::forProject($this->project);
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    public function csv(ProjectReportSection $section): array
    {
        return match ($section) {
            ProjectReportSection::Summary => $this->summaryCsv(),
            ProjectReportSection::Progress => $this->progressCsv(),
            ProjectReportSection::Procurement => $this->procurementCsv(),
            ProjectReportSection::Cost => $this->costCsv(),
            ProjectReportSection::Payment => $this->paymentCsv(),
            ProjectReportSection::Billing => $this->billingCsv(),
            ProjectReportSection::Assets => $this->assetsCsv(),
            ProjectReportSection::Documents => $this->documentsCsv(),
        };
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function summaryCsv(): array
    {
        $summary = $this->summary();

        $rows = [['Nilai Kontrak', $summary['contract_value']]];

        if ($summary['progress'] !== null) {
            $rows[] = ['Nilai BOQ (Approved)', $summary['progress']['boq_value']];
            $rows[] = ['Progress (%)', $summary['progress']['progress']];
            $rows[] = ['Earned Value', $summary['progress']['earned_value']];
        }

        if ($summary['cost'] !== null) {
            $rows[] = ['Actual BMHP', $summary['cost']['bmhp']];
            $rows[] = ['Actual Sewa Alat', $summary['cost']['rental']];
            $rows[] = ['Actual Payment', $summary['cost']['payment']];
            $rows[] = ['Actual Man Power', $summary['cost']['man_power']];
            $rows[] = ['Total Actual Cost', $summary['cost']['actual']];
            $rows[] = ['Actual vs BOQ (%)', $summary['cost']['percentage']];
            $rows[] = ['Sisa Budget (BOQ - Actual)', $summary['cost']['variance']];
        }

        if ($summary['payment'] !== null) {
            $rows[] = ['Payment Plan', $summary['payment']['planned']];
            $rows[] = ['Payment Dibayar', $summary['payment']['paid']];
        }

        if ($summary['procurement'] !== null) {
            foreach (ProcurementType::cases() as $type) {
                $rows[] = ['Pengadaan '.$type->label().' Dibayar', $summary['procurement']['paid'][$type->value]];
            }

            $rows[] = ['Permintaan Dalam Proses', $summary['procurement']['waiting']];
        }

        if ($summary['billing'] !== null) {
            $rows[] = ['Total Ditagih ke Client', $summary['billing']['billed']];
            $rows[] = ['Diterima dari Client', $summary['billing']['received']];
            $rows[] = ['Piutang', $summary['billing']['outstanding']];
        }

        if ($summary['assets'] !== null) {
            $rows[] = ['Aset Digunakan', $summary['assets']['in_use']];
        }

        if ($summary['documents'] !== null) {
            $rows[] = ['Dokumen', $summary['documents']['total']];
            $rows[] = ['Dokumen Menunggu', $summary['documents']['waiting']];
            $rows[] = ['Dokumen Approved', $summary['documents']['approved']];
        }

        return [
            'headers' => ['metrik', 'nilai'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function progressCsv(): array
    {
        return [
            'headers' => ['boq', 'cogs_code', 'cogs_name', 'item_code', 'item_name', 'quantity', 'unit', 'boq_value', 'weight_percent', 'progress_percent', 'earned_value', 'status'],
            'rows' => $this->boqItems()->map(fn (BoqItem $item) => [
                $item->cogs?->boq?->code,
                $item->cogs?->code,
                $item->cogs?->name,
                $item->code,
                $item->name,
                (float) $item->quantity,
                $item->unit,
                (float) $item->total_price,
                round($item->weightAgainstBoq(), 2),
                (float) $item->progress_percentage,
                round((float) $item->total_price * (float) $item->progress_percentage / 100, 2),
                $item->progressStatus()->value,
            ])->all(),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function procurementCsv(): array
    {
        $rows = [];

        foreach ($this->procurement()['requests'] as $request) {
            foreach ($request->items as $item) {
                $rows[] = [
                    $request->request_number,
                    $request->type->label(),
                    $request->request_date?->format('Y-m-d'),
                    $request->status->label(),
                    $request->supplier,
                    $item->item_name,
                    $item->unit,
                    (float) $item->quantity,
                    (float) $item->unit_price,
                    (float) $item->total,
                    $item->paid_unit_price !== null ? (float) $item->paid_unit_price : null,
                    $item->paid_total !== null ? (float) $item->paid_total : null,
                    $request->type->isAllocatable() && $request->isPaid() ? $item->allocatedAmount() : null,
                    $request->payment_date?->format('Y-m-d'),
                    $request->invoice_number,
                    $request->creator?->name,
                ];
            }
        }

        return [
            'headers' => ['request_number', 'type', 'request_date', 'status', 'supplier', 'item_name', 'unit', 'quantity', 'unit_price', 'estimated_total', 'paid_unit_price', 'paid_total', 'allocated_amount', 'payment_date', 'invoice_number', 'requested_by'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function costCsv(): array
    {
        $cost = $this->cost();
        $manPowerRows = $cost['summary']['man_power'] > 0
            ? [[null, null, null, 'Man Power (biaya level project)', null, null, null, null, $cost['summary']['man_power'], null, null, null, null]]
            : [];

        return [
            'headers' => ['boq', 'cogs_code', 'item_code', 'item_name', 'boq_value', 'bmhp_actual', 'rental_actual', 'payment_actual', 'actual_cost', 'remaining_budget', 'actual_percent', 'variance_status', 'progress_percent'],
            'rows' => [...$cost['rows']->map(fn (array $row) => [
                $row['boq_code'],
                $row['cogs']?->code,
                $row['item']->code,
                $row['item']->name,
                $row['boq_value'],
                $row['bmhp'],
                $row['rental'],
                $row['payment'],
                $row['actual'],
                $row['variance'],
                $row['percentage'],
                $row['status']->label(),
                $row['progress'],
            ])->all(), ...$manPowerRows],
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function paymentCsv(): array
    {
        return [
            'headers' => ['payment_code', 'payment_plan', 'payment_date', 'amount', 'payment_method', 'reference_number', 'status', 'overrun_reason', 'void_reason'],
            'rows' => $this->payment()['payments']->map(fn (Payment $payment) => [
                $payment->payment_code,
                $payment->paymentPlan?->reference_code,
                $payment->payment_date?->format('Y-m-d'),
                (float) $payment->amount,
                $payment->payment_method,
                $payment->reference_number,
                $payment->status->label(),
                $payment->overrun_reason,
                $payment->void_reason,
            ])->all(),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function billingCsv(): array
    {
        return [
            'headers' => ['billing_number', 'term', 'billing_date', 'invoice_number', 'invoice_date', 'due_date', 'billing_amount', 'received', 'outstanding', 'status'],
            'rows' => $this->billing()['billings']->map(fn (ClientBilling $billing) => [
                $billing->billing_number,
                $billing->term?->displayName(),
                $billing->billing_date?->format('Y-m-d'),
                $billing->invoice_number,
                $billing->invoice_date?->format('Y-m-d'),
                $billing->due_date?->format('Y-m-d'),
                (float) $billing->billing_amount,
                $billing->receivedAmount(),
                $billing->outstandingAmount(),
                $billing->displayStatus()->label(),
            ])->all(),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function assetsCsv(): array
    {
        return [
            'headers' => ['asset_code', 'asset_name', 'category', 'source', 'procurement_request', 'assigned_at', 'released_at', 'status', 'notes'],
            'rows' => $this->assets()['assignments']->map(fn (ProjectAsset $assignment) => [
                $assignment->asset?->code,
                $assignment->asset?->name,
                $assignment->asset?->category,
                $assignment->asset?->source_type?->label(),
                $assignment->asset?->procurementRequestItem?->request?->request_number,
                $assignment->assigned_at?->format('Y-m-d H:i'),
                $assignment->released_at?->format('Y-m-d H:i'),
                $assignment->status->label(),
                $assignment->notes,
            ])->all(),
        ];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string|int|float|null>>}
     */
    private function documentsCsv(): array
    {
        $monitor = $this->documents();

        return [
            'headers' => ['document_number', 'title', 'type', 'reference', 'status', 'version', 'status_since', 'revision_count', 'cycle_duration'],
            'rows' => $monitor->documents->map(fn (ProjectDocument $document) => [
                $document->document_number,
                $document->document_title,
                $document->documentType?->name,
                $document->referenceLabel(),
                $document->current_status->label(),
                $document->current_version,
                $document->statusSince()?->format('Y-m-d H:i'),
                $document->revisionCount(),
                ProjectDocumentMonitor::formatDuration(ProjectDocumentMonitor::cycleSeconds($document, $monitor->now)),
            ])->all(),
        ];
    }

    /**
     * @return array{bmhp: float, rental: float, payment: float, man_power: float, actual: float, percentage: float, variance: float, status: ?ProjectVarianceStatus}
     */
    private function costSummary(float $boqValue): array
    {
        $actual = $this->actualCost()->projectActual();

        return [
            'bmhp' => $this->actualCost()->bmhpActual(),
            'rental' => $this->actualCost()->rentalActual(),
            'payment' => $this->actualCost()->paymentActual(),
            'man_power' => $this->actualCost()->manPowerActual(),
            'actual' => $actual,
            'percentage' => ProjectActualCost::percentage($boqValue, $actual),
            'variance' => ProjectActualCost::variance($boqValue, $actual),
            'status' => $boqValue > 0 ? ProjectVarianceStatus::fromAmounts($boqValue, $actual) : null,
        ];
    }

    /**
     * @return array{planned: float, paid: float}
     */
    private function paymentPlanTotals(): array
    {
        $plans = $this->paymentPlans();

        return [
            'planned' => round((float) $plans->sum(fn (PaymentPlan $plan) => (float) $plan->amount), 2),
            'paid' => round((float) $plans->sum(fn (PaymentPlan $plan) => $plan->paidAmount()), 2),
        ];
    }

    /**
     * @return array{paid: array<string, float>, waiting: int}
     */
    private function procurementTotals(): array
    {
        $requests = $this->project->procurementRequests()->get(['id', 'type', 'status', 'paid_total']);
        $paid = $requests->where('status', ProcurementStatus::Paid);

        return [
            'paid' => collect(ProcurementType::cases())->mapWithKeys(fn (ProcurementType $type) => [
                $type->value => round((float) $paid->where('type', $type)->sum(fn (ProcurementRequest $request) => (float) $request->paid_total), 2),
            ])->all(),
            'waiting' => $requests
                ->whereIn('status', [ProcurementStatus::Draft, ProcurementStatus::Submitted, ProcurementStatus::Approved])
                ->count(),
        ];
    }

    /**
     * @return array{total: int, waiting: int, approved: int}
     */
    private function documentTotals(): array
    {
        $documents = $this->documents();

        return [
            'total' => $documents->total(),
            'waiting' => $documents->waitingClientCount() + $documents->waitingAdminCount(),
            'approved' => $documents->approvedCount(),
        ];
    }

    /**
     * @return Collection<int, PaymentPlan>
     */
    private function paymentPlans(): Collection
    {
        return PaymentPlan::query()
            ->whereIn('boq_id', $this->project->approvedBoqs->pluck('id'))
            ->with(['boq', 'payments'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, BoqItem>
     */
    private function boqItems(): Collection
    {
        return $this->project->approvedBoqs
            ->flatMap(fn (Boq $boq) => $boq->cogs->sortBy('sort_order')->flatMap(
                fn (BoqCogs $cogs) => $cogs->items->sortBy('sort_order')->each(function (BoqItem $item) use ($cogs, $boq) {
                    $cogs->setRelation('boq', $boq);
                    $item->setRelation('cogs', $cogs);
                })
            ))
            ->values();
    }

    private function inPeriod(?\DateTimeInterface $date): bool
    {
        if ($date === null) {
            return ! $this->hasPeriod();
        }

        $day = CarbonImmutable::instance($date);

        return ($this->from === null || $day->gte($this->from))
            && ($this->to === null || $day->lte($this->to));
    }
}
