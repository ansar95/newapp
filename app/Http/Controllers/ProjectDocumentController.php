<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateProjectDocument;
use App\Actions\Document\UpdateProjectDocument;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Support\ProjectDetailContext;
use App\Support\ProjectDocumentMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectDocumentController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [ProjectDocument::class, $project]);

        return view('projects.documents.index', [
            'context' => ProjectDetailContext::for($project, $request->user()),
            'project' => $project,
            'tab' => 'documents',
            'monitor' => ProjectDocumentMonitor::forProject($project),
            'canCreate' => $request->user()->can('create', [ProjectDocument::class, $project]),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProjectDocument::class, $project]);

        return view('project-documents.create', [
            'project' => $project,
        ]);
    }

    public function store(Request $request, Project $project, CreateProjectDocument $createProjectDocument): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProjectDocument::class, $project]);

        $document = $createProjectDocument->handle(
            $request->user(),
            $project,
            $request->only(['type_label', 'document_title', 'description', 'version_notes']),
            $request->file('file'),
            true
        );

        return redirect()
            ->route('project-documents.show', $document)
            ->with('success', 'Dokumen '.$document->document_number.' berhasil diarsipkan.');
    }

    public function show(ProjectDocument $projectDocument): View
    {
        $this->authorize('view', $projectDocument);

        $projectDocument->load([
            'project',
            'documentType',
            'creator',
            'versions.uploader',
            'reference' => fn ($morph) => $morph->morphWith([ClientBilling::class => ['term']]),
        ]);

        return view('project-documents.show', [
            'document' => $projectDocument,
            'project' => $projectDocument->project,
        ]);
    }

    public function edit(ProjectDocument $projectDocument): View
    {
        $this->authorize('update', $projectDocument);

        $projectDocument->load(['project', 'documentType']);

        return view('project-documents.edit', [
            'document' => $projectDocument,
            'project' => $projectDocument->project,
        ]);
    }

    public function update(Request $request, ProjectDocument $projectDocument, UpdateProjectDocument $updateProjectDocument): RedirectResponse
    {
        $this->authorize('update', $projectDocument);

        $updateProjectDocument->handle($request->user(), $projectDocument, $request->only(['type_label', 'document_title', 'description']));

        return redirect()
            ->route('project-documents.show', $projectDocument)
            ->with('success', 'Dokumen berhasil diperbarui.');
    }
}
