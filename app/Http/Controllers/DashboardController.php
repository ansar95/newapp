<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
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

        return view('dashboard', [
            'contractEndingProjects' => $contractEndingProjects,
            'totalProjects' => $totalProjects,
            'activeProjects' => $activeProjects,
            'completedProjects' => $completedProjects,
            'recentProjects' => $recentProjects,
        ]);
    }
}
