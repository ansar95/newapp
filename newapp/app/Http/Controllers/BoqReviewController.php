<?php

namespace App\Http\Controllers;

use App\Actions\BOQ\ConfirmBOQ;
use App\Actions\BOQ\RequestRevision;
use App\Enums\BoqStatus;
use App\Enums\Permission;
use App\Models\Boq;
use App\Models\BoqRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoqReviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission(Permission::BoqReview), 403);

        $pendingBoqs = Boq::query()
            ->where('status', BoqStatus::WaitingApproval)
            ->whereHas('project')
            ->with(['project', 'submitter', 'cogs.items'])
            ->orderBy('submitted_at')
            ->get();

        return view('boqs.approvals', [
            'pendingBoqs' => $pendingBoqs,
        ]);
    }

    public function show(Boq $boq): View
    {
        $this->authorize('review', $boq);

        $boq->load(['project', 'cogs.items']);

        return view('boqs.review', [
            'boq' => $boq,
            'project' => $boq->project,
        ]);
    }

    public function confirm(Request $request, Boq $boq, ConfirmBOQ $confirmBOQ): RedirectResponse
    {
        $this->authorize('approve', $boq);

        $confirmBOQ->handle($request->user(), $boq, $request->input('review_notes'));

        return redirect()
            ->route('boqs.show', $boq)
            ->with('success', 'BOQ berhasil dikonfirmasi.');
    }

    public function requestRevision(Request $request, Boq $boq, RequestRevision $requestRevision): RedirectResponse
    {
        $this->authorize('requestRevision', $boq);
        $this->authorize('create', [BoqRevision::class, $boq]);

        $revision = $requestRevision->handle($request->user(), $boq, [
            'reason' => $request->input('reason'),
            'item_ids' => array_map('intval', $request->input('item_ids', [])),
            'item_reasons' => $request->input('item_reasons', []),
        ]);

        return redirect()
            ->route('boqs.revision.show', $boq)
            ->with('success', 'BOQ dikembalikan untuk revisi. Kode: '.$revision->revision_code);
    }
}
