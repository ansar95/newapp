<?php

namespace App\Http\Controllers;

use App\Actions\Project\ActivateProject;
use App\Actions\Project\CompleteProject;
use App\Actions\Project\CreateProject;
use App\Actions\Project\DeleteProject;
use App\Actions\Project\UpdateProject;
use App\Enums\ProjectAssetStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

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
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'search' => $search,
            'status' => $status,
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create');
    }

    public function store(Request $request, CreateProject $createProject): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = $createProject->handle($request->user(), $request->all());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project berhasil dibuat.');
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $context = ProjectDetailContext::for($project, $request->user());

        return view('projects.show', [
            'context' => $context,
            'project' => $project,
            'tab' => 'overview',
            'activeAssetCount' => $request->user()->can('delete', $project)
                ? $project->projectAssets()->where('status', ProjectAssetStatus::InUse)->count()
                : 0,
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project, UpdateProject $updateProject): RedirectResponse
    {
        $this->authorize('update', $project);

        $updateProject->handle($request->user(), $project, $request->all());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project berhasil diperbarui.');
    }

    public function activate(Project $project, ActivateProject $activateProject): RedirectResponse
    {
        $this->authorize('activate', $project);

        $activateProject->handle(request()->user(), $project);

        return back()->with('success', 'Project berhasil diaktifkan.');
    }

    public function complete(Project $project, CompleteProject $completeProject): RedirectResponse
    {
        $this->authorize('complete', $project);

        $completeProject->handle(request()->user(), $project);

        return back()->with('success', 'Project berhasil diselesaikan.');
    }

    public function destroy(Request $request, Project $project, DeleteProject $deleteProject): RedirectResponse
    {
        $this->authorize('delete', $project);

        $deleteProject->handle($request->user(), $project, $request->only('confirmation_code'));

        return redirect()
            ->route('projects.index')
            ->with('success', "Project {$project->code} berhasil dihapus.");
    }
}
