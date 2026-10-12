<?php

namespace App\Http\Controllers;

use App\Actions\Asset\AssignAssetToProject;
use App\Actions\Asset\ReleaseAssetFromProject;
use App\Actions\Asset\UpdateAssetResponsible;
use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectAssetController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [ProjectAsset::class, $project]);

        $context = ProjectDetailContext::for($project, $request->user());

        $assignments = $project->projectAssets()
            ->with(['asset', 'assignedBy', 'releasedBy'])
            ->latest('assigned_at')
            ->get();

        $availableAssets = Asset::query()
            ->where('status', AssetStatus::Available)
            ->whereDoesntHave('projectAssets', function ($query) {
                $query->where('status', ProjectAssetStatus::InUse);
            })
            ->orderBy('code')
            ->get();

        return view('projects.assets.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'assets',
            'assignments' => $assignments,
            'activeCount' => $assignments->where('status', ProjectAssetStatus::InUse)->count(),
            'releasedCount' => $assignments->where('status', ProjectAssetStatus::Released)->count(),
            'availableAssets' => $availableAssets,
        ]);
    }

    public function store(Request $request, Project $project, AssignAssetToProject $assignAssetToProject): RedirectResponse
    {
        $this->authorize('assign', [ProjectAsset::class, $project]);

        $assignAssetToProject->handle($request->user(), $project, $request->all());

        return back()->with('success', 'Asset berhasil digunakan pada project.');
    }

    public function updateResponsible(
        Request $request,
        Project $project,
        ProjectAsset $projectAsset,
        UpdateAssetResponsible $updateAssetResponsible
    ): RedirectResponse {
        $this->authorize('updateResponsible', $projectAsset);

        abort_unless((int) $projectAsset->project_id === (int) $project->id, 404);

        $updateAssetResponsible->handle($project, $projectAsset, $request->only(['responsible_name', 'responsible_position']));

        return back()->with('success', 'Penanggung jawab asset berhasil diperbarui.');
    }

    public function release(
        Request $request,
        Project $project,
        ProjectAsset $projectAsset,
        ReleaseAssetFromProject $releaseAssetFromProject
    ): RedirectResponse {
        $this->authorize('release', $projectAsset);

        abort_unless((int) $projectAsset->project_id === (int) $project->id, 404);

        $releaseAssetFromProject->handle($request->user(), $project, $projectAsset, $request->all());

        return back()->with('success', 'Asset berhasil di-release dan kembali AVAILABLE.');
    }
}
