<?php

namespace App\Support;

use App\Enums\AssetStatus;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVarianceStatus;
use App\Models\Asset;
use App\Models\PaymentPlan;
use App\Models\Project;
use Illuminate\Support\Collection;

class ReportData
{
    /**
     * @return array{
     *     projects_total: int,
     *     projects_active: int,
     *     projects_completed: int,
     *     projects_draft: int,
     *     total_boq: float,
     *     total_paid: float,
     *     total_remaining: float,
     *     assets_available: int,
     *     assets_in_use: int,
     *     assets_requested: int,
     *     avg_progress: float
     * }
     */
    public function hubSummary(): array
    {
        $projects = Project::query()->with(['approvedBoqs.cogs.items', 'approvedBoqs.paymentPlans.payments'])->get();

        $totalBoq = round($projects->sum(fn (Project $project) => $project->totalCost()), 2);
        $totalPaid = 0.0;

        foreach ($projects as $project) {
            foreach ($project->approvedBoqs as $boq) {
                foreach ($boq->paymentPlans as $plan) {
                    $totalPaid += $plan->paidAmount();
                }
            }
        }

        $totalPaid = round($totalPaid, 2);
        $progressValues = $projects
            ->filter(fn (Project $project) => $project->totalCost() > 0)
            ->map(fn (Project $project) => $project->progressPercentage());

        return [
            'projects_total' => $projects->count(),
            'projects_active' => $projects->where('status', ProjectStatus::Active)->count(),
            'projects_completed' => $projects->where('status', ProjectStatus::Completed)->count(),
            'projects_draft' => $projects->where('status', ProjectStatus::Draft)->count(),
            'total_boq' => $totalBoq,
            'total_paid' => $totalPaid,
            'total_remaining' => max(0, round($totalBoq - $totalPaid, 2)),
            'assets_available' => Asset::query()->where('status', AssetStatus::Available)->count(),
            'assets_in_use' => Asset::query()->where('status', AssetStatus::InUse)->count(),
            'assets_requested' => Asset::query()->where('status', AssetStatus::Requested)->count(),
            'avg_progress' => $progressValues->isEmpty()
                ? 0.0
                : round((float) $progressValues->avg(), 1),
        ];
    }

    /**
     * @param  array{search?: string, status?: string}  $filters
     * @return array{summary: array<string, int|float>, rows: Collection<int, array<string, mixed>>}
     */
    public function projects(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $projects = Project::query()
            ->with(['approvedBoqs.cogs.items'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('contract_number', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('code')
            ->get();

        $rows = $projects->map(fn (Project $project) => [
            'code' => $project->code,
            'name' => $project->name,
            'client_name' => $project->client_name,
            'contract_number' => $project->contract_number,
            'contract_value' => $project->contract_value,
            'status' => $project->status,
            'progress' => $project->progressPercentage(),
            'boq_value' => $project->totalCost(),
            'project' => $project,
        ]);

        return [
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->where('status', ProjectStatus::Active)->count(),
                'completed' => $rows->where('status', ProjectStatus::Completed)->count(),
                'draft' => $rows->where('status', ProjectStatus::Draft)->count(),
                'total_boq' => round($rows->sum('boq_value'), 2),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{project_id?: string|int|null, payment_status?: string}  $filters
     * @return array{summary: array<string, float|int>, rows: Collection<int, array<string, mixed>>}
     */
    public function finance(array $filters = []): array
    {
        $projectId = $filters['project_id'] ?? null;
        $paymentStatus = trim((string) ($filters['payment_status'] ?? ''));

        $plans = PaymentPlan::query()
            ->whereHas('boq.project')
            ->with(['boq.project', 'payments'])
            ->when($projectId, function ($query) use ($projectId) {
                $query->whereHas('boq', fn ($boq) => $boq->where('project_id', $projectId));
            })
            ->when($paymentStatus !== '', fn ($query) => $query->where('status', $paymentStatus))
            ->latest('id')
            ->get();

        $rows = $plans->map(function (PaymentPlan $plan) {
            $project = $plan->boq?->project;
            $boqValue = $plan->boq?->totalAmount() ?? 0.0;
            $paid = $plan->paidAmount();

            return [
                'project_code' => $project?->code ?? '—',
                'project_name' => $project?->name ?? '—',
                'boq_code' => $plan->boq?->code ?? '—',
                'reference_code' => $plan->reference_code,
                'planned_amount' => (float) $plan->amount,
                'paid_amount' => $paid,
                'remaining' => max(0, (float) $plan->amount - $paid),
                'status' => $plan->status,
                'boq_value' => $boqValue,
                'plan' => $plan,
            ];
        });

        $totalPlanned = round($rows->sum('planned_amount'), 2);
        $totalPaid = round($rows->sum('paid_amount'), 2);

        return [
            'summary' => [
                'plans' => $rows->count(),
                'total_planned' => $totalPlanned,
                'total_paid' => $totalPaid,
                'total_remaining' => max(0, round($totalPlanned - $totalPaid, 2)),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{project_id?: string|int|null, status?: string}  $filters
     * @return array{summary: array<string, float|int>, rows: Collection<int, array<string, mixed>>}
     */
    public function progress(array $filters = []): array
    {
        $projectId = $filters['project_id'] ?? null;
        $status = trim((string) ($filters['status'] ?? ''));

        $projects = Project::query()
            ->with(['approvedBoqs.cogs.items'])
            ->when($projectId, fn ($query) => $query->whereKey($projectId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('code')
            ->get();

        $rows = $projects->map(fn (Project $project) => [
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
            'progress' => $project->progressPercentage(),
            'earned_value' => $project->earnedValue(),
            'boq_value' => $project->totalCost(),
            'project' => $project,
        ]);

        $withBoq = $rows->filter(fn (array $row) => $row['boq_value'] > 0);

        return [
            'summary' => [
                'total' => $rows->count(),
                'avg_progress' => $withBoq->isEmpty() ? 0.0 : round((float) $withBoq->avg('progress'), 1),
                'below_50' => $withBoq->filter(fn (array $row) => $row['progress'] < 50)->count(),
                'between_50_99' => $withBoq->filter(fn (array $row) => $row['progress'] >= 50 && $row['progress'] < 100)->count(),
                'complete' => $withBoq->filter(fn (array $row) => $row['progress'] >= 100)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * ACTIVE and COMPLETED projects with an approved BOQ, worst actual cost percentage first.
     *
     * @param  array{variance?: string}  $filters
     * @return array{
     *     summary: array{total: int, over_budget: int, warning: int, normal: int, total_boq: float, total_actual: float, total_variance: float},
     *     rows: Collection<int, array<string, mixed>>
     * }
     */
    public function variance(array $filters = []): array
    {
        $variance = ProjectVarianceStatus::tryFrom(trim((string) ($filters['variance'] ?? '')));

        $allRows = Project::query()
            ->with(['approvedBoqs.cogs.items'])
            ->whereIn('status', [ProjectStatus::Active, ProjectStatus::Completed])
            ->orderBy('code')
            ->get()
            ->filter(fn (Project $project) => $project->totalCost() > 0)
            ->map(function (Project $project) {
                $actualCost = ProjectActualCost::forProject($project);
                $boqValue = $project->totalCost();
                $actual = $actualCost->projectActual();

                return [
                    'code' => $project->code,
                    'name' => $project->name,
                    'client_name' => $project->client_name,
                    'status' => $project->status,
                    'boq_value' => $boqValue,
                    'bmhp_actual' => $actualCost->bmhpActual(),
                    'rental_actual' => $actualCost->rentalActual(),
                    'payment_actual' => $actualCost->paymentActual(),
                    'man_power_actual' => $actualCost->manPowerActual(),
                    'actual_cost' => $actual,
                    'variance' => ProjectActualCost::variance($boqValue, $actual),
                    'actual_percentage' => ProjectActualCost::percentage($boqValue, $actual),
                    'progress' => $project->progressPercentage(),
                    'variance_status' => ProjectVarianceStatus::fromAmounts($boqValue, $actual),
                    'project' => $project,
                ];
            })
            ->sortByDesc('actual_percentage')
            ->values();

        return [
            'summary' => [
                'total' => $allRows->count(),
                'over_budget' => $allRows->where('variance_status', ProjectVarianceStatus::OverBudget)->count(),
                'warning' => $allRows->where('variance_status', ProjectVarianceStatus::Warning)->count(),
                'normal' => $allRows->where('variance_status', ProjectVarianceStatus::Normal)->count(),
                'total_boq' => round($allRows->sum('boq_value'), 2),
                'total_actual' => round($allRows->sum('actual_cost'), 2),
                'total_variance' => round($allRows->sum('variance'), 2),
            ],
            'rows' => $variance === null
                ? $allRows
                : $allRows->where('variance_status', $variance)->values(),
        ];
    }

    /**
     * @param  array{search?: string, status?: string, source?: string, condition?: string, category?: string}  $filters
     * @return array{summary: array<string, int>, rows: Collection<int, Asset>}
     */
    public function assets(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $source = trim((string) ($filters['source'] ?? ''));
        $condition = trim((string) ($filters['condition'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));

        $assets = Asset::query()
            ->with(['activeAssignment.project'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($source !== '', fn ($query) => $query->where('source_type', $source))
            ->when($condition !== '', fn ($query) => $query->where('condition', $condition))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('code')
            ->get();

        return [
            'summary' => [
                'total' => $assets->count(),
                'available' => $assets->where('status', AssetStatus::Available)->count(),
                'in_use' => $assets->where('status', AssetStatus::InUse)->count(),
                'requested' => $assets->where('status', AssetStatus::Requested)->count(),
                'inactive' => $assets->where('status', AssetStatus::Inactive)->count(),
            ],
            'rows' => $assets,
        ];
    }
}
