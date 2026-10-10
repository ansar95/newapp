<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\AllocateProcurementItem;
use App\Enums\BoqStatus;
use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcurementAllocationController extends Controller
{
    public function bmhp(Request $request, Project $project): View
    {
        return $this->index($request, $project, ProcurementType::Bmhp);
    }

    public function rentals(Request $request, Project $project): View
    {
        return $this->index($request, $project, ProcurementType::Rental);
    }

    public function update(Request $request, ProcurementRequestItem $procurementRequestItem, AllocateProcurementItem $allocateProcurementItem): RedirectResponse
    {
        $this->authorize('allocate', $procurementRequestItem->request);

        $rows = collect($request->input('allocations', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['selected']))
            ->map(fn (array $row) => [
                'boq_item_id' => $row['boq_item_id'] ?? null,
                'allocated_amount' => $row['allocated_amount'] ?? null,
                'allocated_quantity' => $row['allocated_quantity'] ?? null,
            ])
            ->values()
            ->all();

        $allocateProcurementItem->handle($request->user(), $procurementRequestItem, $rows);

        return back()->with('success', 'Alokasi '.$procurementRequestItem->item_name.' berhasil disimpan.');
    }

    private function index(Request $request, Project $project, ProcurementType $type): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [ProcurementRequest::class, $project]);

        $context = ProjectDetailContext::for($project, $request->user());

        $paidRequests = $project->procurementRequests()
            ->where('type', $type)
            ->where('status', ProcurementStatus::Paid)
            ->with('items.allocations.boqItem.cogs.boq')
            ->latest('payment_date')
            ->latest('id')
            ->get();

        $canAllocate = $paidRequests->isNotEmpty() && $request->user()->can('allocate', $paidRequests->first());

        return view('projects.procurement.allocations', [
            'context' => $context,
            'project' => $project,
            'tab' => $type === ProcurementType::Bmhp ? 'bmhp' : 'rentals',
            'type' => $type,
            'paidRequests' => $paidRequests,
            'actualCost' => $type === ProcurementType::Bmhp ? $context->actualCost->bmhp : $context->actualCost->rental,
            'projectActualCost' => $context->actualCost,
            'canAllocate' => $canAllocate,
            'approvedBoqs' => $canAllocate
                ? $project->boqs()->where('status', BoqStatus::Approved)->with('cogs.items')->orderBy('id')->get()
                : collect(),
            'pendingCount' => $project->procurementRequests()
                ->where('type', $type)
                ->whereIn('status', [ProcurementStatus::Draft, ProcurementStatus::Submitted, ProcurementStatus::Approved])
                ->count(),
        ]);
    }
}
