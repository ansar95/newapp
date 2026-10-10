<?php

namespace App\Http\Controllers;

use App\Actions\Billing\RemoveProjectTerm;
use App\Actions\Billing\SaveProjectTerm;
use App\Actions\Billing\SetProjectTermReadiness;
use App\Models\Project;
use App\Models\ProjectTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectTermController extends Controller
{
    private const FIELDS = ['term_name', 'calculation_type', 'percentage', 'amount', 'trigger_type', 'trigger_value', 'trigger_progress', 'due_date', 'notes'];

    public function store(Request $request, Project $project, SaveProjectTerm $saveProjectTerm): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProjectTerm::class, $project]);

        $term = $saveProjectTerm->handle($request->user(), $project, $request->only(self::FIELDS));

        return redirect()
            ->route('projects.billing.index', $project)
            ->with('success', $term->displayName().' ditambahkan. Tandai READY saat termin siap ditagihkan.');
    }

    public function update(Request $request, ProjectTerm $projectTerm, SaveProjectTerm $saveProjectTerm): RedirectResponse
    {
        $this->authorize('update', $projectTerm);

        $term = $saveProjectTerm->handle($request->user(), $projectTerm->project, $request->only(self::FIELDS), $projectTerm);

        return redirect()
            ->route('projects.billing.index', $term->project_id)
            ->with('success', $term->displayName().' diperbarui.');
    }

    public function readiness(Request $request, ProjectTerm $projectTerm, SetProjectTermReadiness $setProjectTermReadiness): RedirectResponse
    {
        $this->authorize('update', $projectTerm);

        $term = $setProjectTermReadiness->handle($request->user(), $projectTerm, $request->boolean('ready'));

        return back()->with('success', $term->displayName().' sekarang '.($term->isReady() ? 'READY untuk ditagihkan.' : 'NOT READY.'));
    }

    public function destroy(Request $request, ProjectTerm $projectTerm, RemoveProjectTerm $removeProjectTerm): RedirectResponse
    {
        $this->authorize('delete', $projectTerm);

        $name = $projectTerm->displayName();
        $deleted = $removeProjectTerm->handle($request->user(), $projectTerm);

        return back()->with('success', $deleted ? $name.' dihapus.' : $name.' dibatalkan. Riwayat penagihannya tetap tersimpan.');
    }
}
