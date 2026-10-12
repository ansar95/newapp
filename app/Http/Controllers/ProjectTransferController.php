<?php

namespace App\Http\Controllers;

use App\Actions\Project\ExportProject;
use App\Actions\Project\ImportProject;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectTransferController extends Controller
{
    public function export(Project $project, ExportProject $exportProject): BinaryFileResponse
    {
        $this->authorize('export', $project);

        $filename = 'project-'.Str::slug($project->code).'-'.now()->format('Ymd-His').'.zip';

        return response()
            ->download($exportProject->handle($project), $filename, ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function import(Request $request, ImportProject $importProject): RedirectResponse
    {
        $this->authorize('import', Project::class);

        $project = $importProject->handle($request->user(), $request->only('file'));

        return redirect()
            ->route('projects.show', $project)
            ->with('success', "Project {$project->code} berhasil diimport.");
    }
}
