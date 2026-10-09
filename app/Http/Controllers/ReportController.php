<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVarianceStatus;
use App\Models\Asset;
use App\Models\Project;
use App\Support\ReportCsv;
use App\Support\ReportData;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportData $reportData,
    ) {}

    public function index(): View
    {
        $this->authorizeReport();

        return view('reports.index', [
            'summary' => $this->reportData->hubSummary(),
        ]);
    }

    public function projects(Request $request): View
    {
        $this->authorizeReport();

        $filters = [
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
        ];

        $report = $this->reportData->projects($filters);

        return view('reports.projects', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'filters' => $filters,
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    public function exportProjects(Request $request): StreamedResponse
    {
        $this->authorizeReport();

        $report = $this->reportData->projects([
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
        ]);

        $csvRows = $report['rows']->map(fn (array $row) => [
            $row['code'],
            $row['name'],
            $row['client_name'],
            $row['contract_number'] ?? '',
            $row['contract_value'] ?? '',
            $row['status'] instanceof \BackedEnum ? $row['status']->value : (string) $row['status'],
            $row['progress'],
            $row['boq_value'],
        ])->all();

        return ReportCsv::download(
            'report-projects-'.now()->format('Ymd-His').'.csv',
            ['code', 'name', 'client', 'contract_number', 'contract_value', 'status', 'progress_percent', 'boq_value'],
            $csvRows
        );
    }

    public function finance(Request $request): View
    {
        $this->authorizeReport();

        $filters = [
            'project_id' => $request->query('project_id'),
            'payment_status' => $request->query('payment_status', ''),
        ];

        $report = $this->reportData->finance($filters);

        return view('reports.finance', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'filters' => $filters,
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
            'paymentStatuses' => PaymentPlanStatus::cases(),
        ]);
    }

    public function exportFinance(Request $request): StreamedResponse
    {
        $this->authorizeReport();

        $report = $this->reportData->finance([
            'project_id' => $request->query('project_id'),
            'payment_status' => $request->query('payment_status', ''),
        ]);

        $csvRows = $report['rows']->map(fn (array $row) => [
            $row['project_code'],
            $row['project_name'],
            $row['boq_code'],
            $row['reference_code'],
            $row['planned_amount'],
            $row['paid_amount'],
            $row['remaining'],
            $row['status'] instanceof \BackedEnum ? $row['status']->value : (string) $row['status'],
        ])->all();

        return ReportCsv::download(
            'report-finance-'.now()->format('Ymd-His').'.csv',
            ['project_code', 'project_name', 'boq_code', 'payment_reference', 'planned', 'paid', 'remaining', 'status'],
            $csvRows
        );
    }

    public function progress(Request $request): View
    {
        $this->authorizeReport();

        $filters = [
            'project_id' => $request->query('project_id'),
            'status' => $request->query('status', ''),
        ];

        $report = $this->reportData->progress($filters);

        return view('reports.progress', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'filters' => $filters,
            'projects' => Project::query()->orderBy('code')->get(['id', 'code', 'name']),
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    public function exportProgress(Request $request): StreamedResponse
    {
        $this->authorizeReport();

        $report = $this->reportData->progress([
            'project_id' => $request->query('project_id'),
            'status' => $request->query('status', ''),
        ]);

        $csvRows = $report['rows']->map(fn (array $row) => [
            $row['code'],
            $row['name'],
            $row['status'] instanceof \BackedEnum ? $row['status']->value : (string) $row['status'],
            $row['progress'],
            $row['earned_value'],
            $row['boq_value'],
        ])->all();

        return ReportCsv::download(
            'report-progress-'.now()->format('Ymd-His').'.csv',
            ['code', 'name', 'status', 'progress_percent', 'earned_value', 'boq_value'],
            $csvRows
        );
    }

    public function variance(Request $request): View
    {
        $this->authorizeReport();

        $filters = ['variance' => (string) $request->query('variance', '')];
        $report = $this->reportData->variance($filters);

        return view('reports.variance', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'filters' => $filters,
            'varianceStatuses' => ProjectVarianceStatus::cases(),
        ]);
    }

    public function assets(Request $request): View
    {
        $this->authorizeReport();

        $filters = [
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
            'source' => $request->query('source', ''),
            'condition' => $request->query('condition', ''),
            'category' => $request->query('category', ''),
        ];

        $report = $this->reportData->assets($filters);

        return view('reports.assets', [
            'summary' => $report['summary'],
            'rows' => $report['rows'],
            'filters' => $filters,
            'statuses' => AssetStatus::cases(),
            'sources' => AssetSourceType::cases(),
            'conditions' => AssetCondition::cases(),
            'categories' => Asset::categoryOptions(),
        ]);
    }

    public function exportAssets(Request $request): StreamedResponse
    {
        $this->authorizeReport();

        $report = $this->reportData->assets([
            'search' => $request->query('search', ''),
            'status' => $request->query('status', ''),
            'source' => $request->query('source', ''),
            'condition' => $request->query('condition', ''),
            'category' => $request->query('category', ''),
        ]);

        $csvRows = $report['rows']->map(fn (Asset $asset) => [
            $asset->code,
            $asset->name,
            $asset->category,
            $asset->source_type->value,
            $asset->status->value,
            $asset->condition?->value ?? '',
            $asset->purchase_year,
            $asset->activeAssignment?->project?->code ?? '',
        ])->all();

        return ReportCsv::download(
            'report-assets-'.now()->format('Ymd-His').'.csv',
            ['code', 'name', 'category', 'source', 'status', 'condition', 'purchase_year', 'current_project'],
            $csvRows
        );
    }

    private function authorizeReport(): void
    {
        abort_unless(
            request()->user()?->hasPermission(Permission::ReportView) ?? false,
            403
        );
    }
}
