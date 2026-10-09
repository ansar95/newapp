<?php

namespace App\Http\Controllers;

use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermTriggerType;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Support\ProjectDetailContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectBillingController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [ClientBilling::class, $project]);

        $context = ProjectDetailContext::for($project, $request->user());

        return view('projects.billing.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'billing',
            'summary' => $context->billingSummary(),
            'canManageTerms' => $request->user()->can('create', [ProjectTerm::class, $project]),
            'canCreateBilling' => $request->user()->can('create', [ClientBilling::class, $project]),
            'calculationTypes' => ProjectTermCalculationType::cases(),
            'triggerTypes' => ProjectTermTriggerType::cases(),
        ]);
    }
}
