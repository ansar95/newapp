<?php

namespace App\Http\Controllers;

use App\Actions\BOQ\CreateBOQ;
use App\Models\Boq;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectBoqController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewAny', Boq::class);

        $context = ProjectDetailContext::for($project, $request->user());

        return view('projects.boq.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'boq',
            'primaryBoq' => $context->primaryBoq,
            'boqs' => $context->boqs,
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('create', Boq::class);

        return view('projects.boq.create', [
            'project' => $project,
            'tab' => 'boq',
            'suggestedCode' => 'BOQ-'.str_replace('-', '', $project->code),
        ]);
    }

    public function store(Request $request, Project $project, CreateBOQ $createBOQ): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', Boq::class);

        $boq = $createBOQ->handle($request->user(), $project, $request->all());

        return redirect()
            ->route('boqs.show', $boq)
            ->with('success', 'BOQ berhasil dibuat.');
    }
}
