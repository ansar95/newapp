<?php

namespace App\Http\Controllers;

use App\Actions\Progress\UpdateItemProgress;
use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\Permission;
use App\Models\BoqItem;
use App\Models\Project;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        Gate::authorize('viewProgress', $project);

        $context = ProjectDetailContext::for($project, $request->user());
        $approvedBoq = $project->approvedBoqs->sortByDesc('id')->first();

        $workflowReady = $approvedBoq !== null
            && $approvedBoq->status === BoqStatus::Approved
            && $context->paymentPlans->contains(
                fn ($plan) => $plan->status === PaymentPlanStatus::Paid
            );

        $cogsProgress = [];
        if ($approvedBoq) {
            $approvedBoq->loadMissing('cogs.items');
            $boqTotal = $approvedBoq->totalAmount();

            foreach ($approvedBoq->cogs as $cogs) {
                $items = $cogs->items->map(function ($item) use ($boqTotal) {
                    $value = (float) $item->total_price;
                    $progress = (float) $item->progress_percentage;

                    return [
                        'model' => $item,
                        'value' => $value,
                        'weight' => $boqTotal > 0 ? round(($value / $boqTotal) * 100, 2) : 0.0,
                        'progress' => $progress,
                        'earned' => round($value * ($progress / 100), 2),
                    ];
                });

                $cogsProgress[] = [
                    'cogs' => $cogs,
                    'progress' => $cogs->progressPercentage(),
                    'items' => $items,
                ];
            }
        }

        $sampleItem = $approvedBoq?->cogs->flatMap->items->first();

        $canUpdate = $workflowReady
            && $request->user() !== null
            && (
                ($sampleItem !== null && $request->user()->can('updateProgress', $sampleItem))
                || ($sampleItem === null && $request->user()->hasPermission(Permission::ProgressUpdate))
            );

        return view('projects.progress.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'progress',
            'approvedBoq' => $approvedBoq,
            'canUpdate' => $canUpdate,
            'cogsProgress' => $cogsProgress,
            'overallProgress' => $context->overallProgress,
            'totalBoqValue' => $context->totalBoqValue,
            'earnedValue' => $context->earnedValue,
        ]);
    }

    public function update(Request $request, BoqItem $item, UpdateItemProgress $updateItemProgress): RedirectResponse
    {
        Gate::authorize('updateProgress', $item);

        $updateItemProgress->handle(
            $request->user(),
            $item,
            $request->input('progress_percentage')
        );

        return back()->with('success', 'Progress berhasil diperbarui.');
    }
}
