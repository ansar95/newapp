<?php

namespace App\Http\Controllers;

use App\Actions\Document\ApproveProjectDocument;
use App\Actions\Document\CancelProjectDocument;
use App\Actions\Document\RequestProjectDocumentRevision;
use App\Actions\Document\ResubmitProjectDocument;
use App\Actions\Document\StartProjectDocumentReview;
use App\Actions\Document\SubmitProjectDocument;
use App\Models\ProjectDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectDocumentWorkflowController extends Controller
{
    public function submit(Request $request, ProjectDocument $projectDocument, SubmitProjectDocument $submitProjectDocument): RedirectResponse
    {
        $this->authorize('submit', $projectDocument);

        $submitProjectDocument->handle($request->user(), $projectDocument, $request->only('notes'));

        return back()->with('success', 'Dokumen disubmit ke client.');
    }

    public function startReview(Request $request, ProjectDocument $projectDocument, StartProjectDocumentReview $startProjectDocumentReview): RedirectResponse
    {
        $this->authorize('startReview', $projectDocument);

        $startProjectDocumentReview->handle($request->user(), $projectDocument, $request->only('notes'));

        return back()->with('success', 'Dokumen dicatat sedang direview client.');
    }

    public function approve(Request $request, ProjectDocument $projectDocument, ApproveProjectDocument $approveProjectDocument): RedirectResponse
    {
        $this->authorize('approve', $projectDocument);

        $approveProjectDocument->handle($request->user(), $projectDocument, $request->only('approval_notes'));

        return back()->with('success', 'Persetujuan client dicatat. Dokumen APPROVED.');
    }

    public function requestRevision(Request $request, ProjectDocument $projectDocument, RequestProjectDocumentRevision $requestProjectDocumentRevision): RedirectResponse
    {
        $this->authorize('requestRevision', $projectDocument);

        $requestProjectDocumentRevision->handle($request->user(), $projectDocument, $request->only('revision_reason'));

        return back()->with('success', 'Permintaan revisi dari client dicatat.');
    }

    public function resubmit(Request $request, ProjectDocument $projectDocument, ResubmitProjectDocument $resubmitProjectDocument): RedirectResponse
    {
        $projectDocument->load('histories');
        $this->authorize('resubmit', $projectDocument);

        $resubmitProjectDocument->handle($request->user(), $projectDocument, $request->only('notes'));

        return back()->with('success', 'Versi revisi dikirim ulang ke client.');
    }

    public function cancel(Request $request, ProjectDocument $projectDocument, CancelProjectDocument $cancelProjectDocument): RedirectResponse
    {
        $this->authorize('cancel', $projectDocument);

        $cancelProjectDocument->handle($request->user(), $projectDocument, $request->only('cancel_reason'));

        return back()->with('success', 'Dokumen dibatalkan.');
    }
}
