<?php

namespace App\Http\Controllers;

use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Payment\MarkPaymentPlanReady;
use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\PaymentPlan;
use App\Models\Project;
use App\Support\ProjectActualCost;
use App\Support\ProjectDetailContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PaymentPlanController extends Controller
{
    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('viewForProject', [PaymentPlan::class, $project]);

        $context = ProjectDetailContext::for($project, $request->user());
        $approvedBoq = $project->approvedBoqs->sortByDesc('id')->first();
        $context->paymentPlans->load(['payments.allocations.boqItem', 'payments.voider']);

        return view('projects.payments.index', [
            'context' => $context,
            'project' => $project,
            'tab' => 'payment',
            'approvedBoq' => $approvedBoq,
            'plans' => $context->paymentPlans,
            'totalBoq' => $context->totalBoqValue,
            'totalPlanned' => $context->totalPlanned,
            'totalPaid' => $context->totalPaid,
            'remaining' => $context->remaining,
            'types' => PaymentPlanType::cases(),
            'planningCogs' => $this->cogsForPlanning($approvedBoq, $context->actualCost),
        ]);
    }

    public function store(Request $request, Project $project, CreatePaymentPlan $createPaymentPlan): RedirectResponse
    {
        $this->authorize('create', [PaymentPlan::class, $project]);

        $approvedBoq = $project->approvedBoqs()->latest('id')->first();

        if ($approvedBoq === null || $approvedBoq->status !== BoqStatus::Approved) {
            return back()->withErrors(['business' => 'Payment Plan tidak dapat dibuat karena BOQ belum APPROVED.']);
        }

        $data = $request->except('items');

        if ($request->has('items')) {
            $data['items'] = collect($request->input('items', []))
                ->filter(fn ($row) => is_array($row) && ! empty($row['selected']))
                ->map(fn (array $row) => [
                    'boq_item_id' => $row['boq_item_id'] ?? null,
                    'planned_amount' => $row['planned_amount'] ?? null,
                ])
                ->values()
                ->all();
        }

        $createPaymentPlan->handle($request->user(), $approvedBoq, $data);

        return back()->with('success', 'Payment plan berhasil dibuat.');
    }

    public function markReady(PaymentPlan $paymentPlan, MarkPaymentPlanReady $markPaymentPlanReady): RedirectResponse
    {
        $this->authorize('markReady', $paymentPlan);

        $markPaymentPlanReady->handle(request()->user(), $paymentPlan);

        return back()->with('success', 'Payment plan siap dibayar.');
    }

    /**
     * Every BOQ item stays selectable: an item can be paid in several plans and may exceed its BOQ value.
     *
     * @return Collection<int, array{
     *     code: string,
     *     name: string,
     *     items: Collection<int, array{item: BoqItem, actual: float, open_planned: float, suggested: float}>
     * }>
     */
    private function cogsForPlanning(?Boq $approvedBoq, ProjectActualCost $actualCost): Collection
    {
        if ($approvedBoq === null) {
            return collect();
        }

        $approvedBoq->loadMissing(['cogs.items', 'paymentPlans.items']);

        $openPlannedByItem = $approvedBoq->paymentPlans
            ->reject(fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::Paid)
            ->flatMap(fn (PaymentPlan $plan) => $plan->items)
            ->groupBy('id')
            ->map(fn (Collection $items) => round((float) $items->sum(fn (BoqItem $item) => (float) $item->pivot->planned_amount), 2));

        return $approvedBoq->cogs
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($cogs) => [
                'code' => $cogs->code,
                'name' => $cogs->name,
                'items' => $cogs->items
                    ->sortBy('sort_order')
                    ->values()
                    ->map(function (BoqItem $item) use ($actualCost, $openPlannedByItem) {
                        $actual = $actualCost->forItem($item);
                        $openPlanned = (float) ($openPlannedByItem[$item->id] ?? 0);

                        return [
                            'item' => $item,
                            'actual' => $actual,
                            'open_planned' => $openPlanned,
                            'suggested' => max(0, round((float) $item->total_price - $actual - $openPlanned, 2)),
                        ];
                    }),
            ])
            ->filter(fn (array $group) => $group['items']->isNotEmpty())
            ->values();
    }
}
