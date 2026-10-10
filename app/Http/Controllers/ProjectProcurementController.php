<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\CreateProcurementRequest;
use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Models\Asset;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectProcurementController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [ProcurementRequest::class, $project]);

        $type = ProcurementType::tryFrom((string) $request->query('type', ''));

        $allRequests = $project->procurementRequests()
            ->withCount('items')
            ->latest('request_date')
            ->latest('id')
            ->get();

        return view('projects.procurement.index', [
            'context' => ProjectDetailContext::for($project, $request->user()),
            'project' => $project,
            'tab' => 'procurement',
            'type' => $type,
            'requests' => $type ? $allRequests->where('type', $type)->values() : $allRequests,
            'summaries' => collect(ProcurementType::cases())->mapWithKeys(fn (ProcurementType $summaryType) => [
                $summaryType->value => [
                    'type' => $summaryType,
                    'waiting' => $allRequests->where('type', $summaryType)
                        ->whereIn('status', [ProcurementStatus::Draft, ProcurementStatus::Submitted, ProcurementStatus::Approved])
                        ->count(),
                    'paid_count' => $allRequests->where('type', $summaryType)->where('status', ProcurementStatus::Paid)->count(),
                    'paid_total' => (float) $allRequests->where('type', $summaryType)->where('status', ProcurementStatus::Paid)->sum('paid_total'),
                ],
            ]),
        ]);
    }

    public function create(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProcurementRequest::class, $project]);

        return view('projects.procurement.create', [
            'project' => $project,
            'type' => ProcurementType::tryFrom((string) $request->query('type', '')) ?? ProcurementType::Bmhp,
            'assetCategories' => Asset::categoryOptions(),
        ]);
    }

    public function store(Request $request, Project $project, CreateProcurementRequest $createProcurementRequest): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ProcurementRequest::class, $project]);

        $procurementRequest = $createProcurementRequest->handle(
            $request->user(),
            $project,
            $request->only(['type', 'request_date', 'supplier', 'notes', 'items'])
        );

        return redirect()
            ->route('procurement-requests.show', $procurementRequest)
            ->with('success', 'Permintaan '.$procurementRequest->request_number.' dibuat sebagai Draft. Ajukan agar diproses Approver.');
    }
}
