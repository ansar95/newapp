<?php

namespace App\Http\Controllers;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Enums\ProjectReportSection;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use App\Support\ProjectReport;
use App\Support\ReportCsv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectReportController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorizeReports($project);

        $section = $this->section($request);
        $report = $this->report($request, $project);

        return view('projects.reports.index', [
            'context' => ProjectDetailContext::for($project, $request->user()),
            'project' => $project,
            'tab' => 'reports',
            'section' => $section,
            'sections' => ProjectReportSection::visibleTo($request->user()),
            'report' => $report,
            'filters' => $this->filters($request),
        ]);
    }

    public function export(Request $request, Project $project): StreamedResponse
    {
        $this->authorizeReports($project);

        $section = $this->section($request);
        $csv = $this->report($request, $project)->csv($section);

        return ReportCsv::download(
            'laporan-'.$project->code.'-'.$section->value.'-'.now()->format('Ymd-His').'.csv',
            $csv['headers'],
            $csv['rows']
        );
    }

    public function print(Request $request, Project $project): View
    {
        $this->authorizeReports($project);

        $printAll = $request->query('section') === 'all';

        return view('projects.reports.print', [
            'project' => $project,
            'sections' => $printAll ? ProjectReportSection::visibleTo($request->user()) : [$this->section($request)],
            'printAll' => $printAll,
            'report' => $this->report($request, $project),
        ]);
    }

    private function authorizeReports(Project $project): void
    {
        $this->authorize('view', $project);
        $this->authorize('viewReports', $project);
    }

    private function section(Request $request): ProjectReportSection
    {
        $section = ProjectReportSection::tryFrom((string) $request->query('section', ''))
            ?? ProjectReportSection::Summary;

        abort_unless($section->isVisibleTo($request->user()), 403);

        return $section;
    }

    private function report(Request $request, Project $project): ProjectReport
    {
        $filters = $this->filters($request);

        return ProjectReport::for(
            $project,
            $request->user(),
            $filters['from'] ? CarbonImmutable::parse($filters['from']) : null,
            $filters['to'] ? CarbonImmutable::parse($filters['to']) : null,
            ProcurementType::tryFrom($filters['type']),
            ProcurementStatus::tryFrom($filters['status']),
        );
    }

    /**
     * @return array{from: ?string, to: ?string, type: string, status: string}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
        ], [
            'to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);

        return [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'type' => (string) ($validated['type'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''),
        ];
    }
}
