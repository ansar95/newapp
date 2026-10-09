<?php

namespace App\Http\Controllers;

use App\Actions\Document\CreateProjectDocument;
use App\Actions\Document\UpdateProjectDocument;
use App\Enums\ClientBillingStatus;
use App\Enums\ProjectDocumentStatus;
use App\Models\ClientBilling;
use App\Models\DocumentType;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Support\ProjectDetailContext;
use App\Support\ProjectDocumentMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

    public function create(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProjectDocument::class, $project]);

        $types = DocumentType::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('project-documents.create', [
            'project' => $project,
            'types' => $types,
            'billingOptions' => $this->billingOptions($project, $types),
            'selectedBillingId' => $request->integer('billing') ?: null,
        ]);
    }

    public function store(Request $request, Project $project, CreateProjectDocument $createProjectDocument): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProjectDocument::class, $project]);

        $document = $createProjectDocument->handle(
            $request->user(),
            $project,
            $request->only(['document_type_id', 'client_billing_id', 'document_title', 'description', 'version_notes']),
            $request->file('file')
        );

        return redirect()
            ->route('project-documents.show', $document)
            ->with('success', 'Dokumen '.$document->document_number.' dibuat sebagai DRAFT dengan versi V1.');
    }

    public function show(ProjectDocument $projectDocument): View
    {
        $this->authorize('view', $projectDocument);

        $projectDocument->load([
            'project',
            'documentType',
            'creator',
            'approver',
            'canceller',
            'versions.uploader',
            'histories.changer',
            'reference' => fn ($morph) => $morph->morphWith([ClientBilling::class => ['term']]),
        ]);

        return view('project-documents.show', [
            'document' => $projectDocument,
            'project' => $projectDocument->project,
            'timeline' => ProjectDocumentMonitor::timeline($projectDocument),
            'statusTotals' => ProjectDocumentMonitor::totalSecondsByStatus($projectDocument),
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

        $updateProjectDocument->handle($request->user(), $projectDocument, $request->only(['document_title', 'description']));

        return redirect()
            ->route('project-documents.show', $projectDocument)
            ->with('success', 'Dokumen berhasil diperbarui.');
    }

    /**
     * Non-cancelled billings that do not have an active billing document yet.
     *
     * @param  Collection<int, DocumentType>  $types
     * @return list<array{id: int, label: string, title: string}>
     */
    private function billingOptions(Project $project, $types): array
    {
        $billingType = $types->first(fn (DocumentType $type) => $type->requiresBilling());

        if ($billingType === null) {
            return [];
        }

        $takenBillingIds = ProjectDocument::query()
            ->where('project_id', $project->id)
            ->where('document_type_id', $billingType->id)
            ->where('current_status', '!=', ProjectDocumentStatus::Cancelled)
            ->pluck('reference_id')
            ->all();

        return $project->clientBillings()
            ->with('term')
            ->where('status', '!=', ClientBillingStatus::Cancelled)
            ->whereNotIn('id', $takenBillingIds)
            ->orderBy('billing_date')
            ->orderBy('id')
            ->get()
            ->map(fn (ClientBilling $billing) => [
                'id' => $billing->id,
                'label' => $billing->billing_number
                    .($billing->invoice_number ? ' / '.$billing->invoice_number : '')
                    .($billing->term ? ' · '.$billing->term->displayName() : '')
                    .' ('.$billing->status->value.')',
                'title' => $billingType->name.($billing->term ? ' '.$billing->term->displayName() : ' '.$billing->billing_number),
            ])
            ->values()
            ->all();
    }
}
