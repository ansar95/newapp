<?php

namespace App\Http\Controllers;

use App\Actions\Bmhp\CreateBmhpPurchase;
use App\Enums\BmhpPurchaseStatus;
use App\Models\BmhpPurchase;
use App\Models\BmhpPurchaseDetail;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectBmhpController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [BmhpPurchase::class, $project]);

        $context = ProjectDetailContext::for($project, $request->user());

        $purchases = $project->bmhpPurchases()
            ->withCount('details')
            ->latest('purchase_date')
            ->latest('id')
            ->get();

        $confirmedItemCount = BmhpPurchaseDetail::query()
            ->whereHas('purchase', fn ($query) => $query
                ->where('project_id', $project->id)
                ->where('status', BmhpPurchaseStatus::Confirmed))
            ->count();

        return view('projects.bmhp.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'bmhp',
            'purchases' => $purchases,
            'actualCost' => $context->bmhpActualCost,
            'confirmedItemCount' => $confirmedItemCount,
            'pendingCount' => $purchases->whereIn('status', [BmhpPurchaseStatus::Draft, BmhpPurchaseStatus::Submitted])->count(),
        ]);
    }

    public function create(Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('create', [BmhpPurchase::class, $project]);

        return view('projects.bmhp.create', [
            'project' => $project,
        ]);
    }

    public function store(Request $request, Project $project, CreateBmhpPurchase $createBmhpPurchase): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [BmhpPurchase::class, $project]);

        $purchase = $createBmhpPurchase->handle(
            $request->user(),
            $project,
            $request->only(['purchase_date', 'supplier', 'invoice_number', 'notes']),
            $request->file('receipt_file')
        );

        return redirect()
            ->route('bmhp-purchases.show', $purchase)
            ->with('success', 'Pembelian '.$purchase->purchase_number.' dibuat. Tambahkan item BMHP lalu alokasikan ke Item BOQ.');
    }
}
