<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Support\SCurveSeries;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $totalProjects = Project::query()->count();
        $activeProjects = Project::query()->where('status', ProjectStatus::Active)->count();
        $completedProjects = Project::query()->where('status', ProjectStatus::Completed)->count();

        $recentProjects = Project::query()
            ->with(['approvedBoqs.cogs.items'])
            ->latest()
            ->limit(8)
            ->get();

        $contractEndingProjects = Project::query()
            ->contractEndingWithin()
            ->orderBy('target_end_date')
            ->get();

        $sCurveProjects = Project::query()->orderBy('code')->get(['id', 'code', 'name']);
        $sCurveProject = null;
        $sCurveSeries = null;

        if ($request->filled('kurva')) {
            $sCurveProject = Project::query()->find($request->integer('kurva'));

            if ($sCurveProject !== null) {
                $this->authorize('view', $sCurveProject);
                $sCurveSeries = SCurveSeries::for($sCurveProject, $this->sCurveDisplay($sCurveProject));
            }
        }

        return view('dashboard', [
            'contractEndingProjects' => $contractEndingProjects,
            'totalProjects' => $totalProjects,
            'activeProjects' => $activeProjects,
            'completedProjects' => $completedProjects,
            'recentProjects' => $recentProjects,
            'sCurveProjects' => $sCurveProjects,
            'sCurveProject' => $sCurveProject,
            'sCurveSeries' => $sCurveSeries,
        ]);
    }

    private function sCurveDisplay(Project $project): SCurveGranularity
    {
        $project->loadMissing('sCurveBaselines');

        $initial = $project->sCurveBaselines->first(
            fn (SCurveBaseline $baseline) => $baseline->kind === SCurveKind::Initial && $baseline->isEstablished()
        );

        return $initial?->granularity ?? SCurveGranularity::Week;
    }
}
