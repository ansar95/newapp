<?php

namespace App\Support;

use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\Permission;
use App\Enums\ProjectAssetStatus;
use App\Enums\ProjectStatus;
use App\Models\Boq;
use App\Models\BoqRevision;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

class ProjectDetailContext
{
    public Project $project;

    public ?Boq $primaryBoq = null;

    /** @var Collection<int, Boq> */
    public Collection $boqs;

    /** @var Collection<int, PaymentPlan> */
    public Collection $paymentPlans;

    public float $totalBoqValue = 0.0;

    public float $earnedValue = 0.0;

    public float $overallProgress = 0.0;

    public int $totalCogs = 0;

    public int $totalItems = 0;

    public float $totalPlanned = 0.0;

    public float $totalPaid = 0.0;

    public float $remaining = 0.0;

    public int $totalAssets = 0;

    public int $activeAssets = 0;

    public int $releasedAssets = 0;

    public ProjectActualCost $actualCost;

    public float $bmhpActual = 0.0;

    public float $rentalActual = 0.0;

    public float $paymentActual = 0.0;

    public float $totalActual = 0.0;

    public float $actualPercentage = 0.0;

    public float $actualVariance = 0.0;

    /**
     * @var list<array{key: string, label: string, state: string, detail: string|null}>
     */
    public array $workflowSteps = [];

    /**
     * @var array{key: string, message: string, button: string, url: string|null}
     */
    public array $nextAction = [];

    private ?ProjectBillingSummary $billingSummary = null;

    public function billingSummary(): ProjectBillingSummary
    {
        return $this->billingSummary ??= ProjectBillingSummary::forProject($this->project);
    }

    private ?ProjectDocumentMonitor $documentMonitor = null;

    public function documentMonitor(): ProjectDocumentMonitor
    {
        return $this->documentMonitor ??= ProjectDocumentMonitor::forProject($this->project);
    }

    public static function for(Project $project, ?User $user = null): self
    {
        $context = new self;
        $context->project = $project;
        $context->hydrate($user);

        return $context;
    }

    public function hydrate(?User $user = null): void
    {
        $this->project->loadMissing([
            'boqs.cogs.items',
            'boqs.revisions',
            'boqs.paymentPlans.payments',
            'boqs.paymentPlans.items',
            'approvedBoqs.cogs.items',
            'approvedBoqs.paymentPlans.payments',
            'approvedBoqs.paymentPlans.items',
            'projectAssets',
            'creator',
        ]);

        $this->boqs = $this->project->boqs->sortByDesc('id')->values();
        $this->primaryBoq = $this->boqs->first();

        $this->totalBoqValue = $this->project->totalCost();
        $this->earnedValue = $this->project->earnedValue();
        $this->overallProgress = $this->project->progressPercentage();

        $this->totalCogs = (int) $this->project->boqs->sum(fn (Boq $boq) => $boq->cogs->count());
        $this->totalItems = (int) $this->project->boqs->sum(
            fn (Boq $boq) => $boq->cogs->sum(fn ($cogs) => $cogs->items->count())
        );

        $this->totalAssets = $this->project->projectAssets->count();
        $this->activeAssets = $this->project->projectAssets
            ->where('status', ProjectAssetStatus::InUse)
            ->count();
        $this->releasedAssets = $this->project->projectAssets
            ->where('status', ProjectAssetStatus::Released)
            ->count();

        $this->actualCost = ProjectActualCost::forProject($this->project);
        $this->bmhpActual = $this->actualCost->bmhpActual();
        $this->rentalActual = $this->actualCost->rentalActual();
        $this->paymentActual = $this->actualCost->paymentActual();
        $this->totalActual = $this->actualCost->projectActual();
        $this->actualPercentage = ProjectActualCost::percentage($this->totalBoqValue, $this->totalActual);
        $this->actualVariance = ProjectActualCost::variance($this->totalBoqValue, $this->totalActual);

        $planBoq = $this->project->approvedBoqs->sortByDesc('id')->first()
            ?? $this->boqs->first(fn (Boq $boq) => $boq->paymentPlans->isNotEmpty());
        $this->paymentPlans = $planBoq
            ? $planBoq->paymentPlans->sortByDesc('id')->values()
            : collect();

        $this->totalPlanned = (float) $this->paymentPlans->sum(fn (PaymentPlan $plan) => (float) $plan->amount);
        $this->totalPaid = round((float) $this->paymentPlans->sum(fn (PaymentPlan $plan) => $plan->paidAmount()), 2);
        $this->remaining = max(0, $this->totalBoqValue - $this->totalPaid);

        $this->workflowSteps = $this->buildWorkflowSteps();
        $this->nextAction = $this->buildNextAction($user ?? auth()->user());
    }

    /**
     * @return list<array{key: string, label: string, state: string, detail: string|null}>
     */
    private function buildWorkflowSteps(): array
    {
        $boq = $this->primaryBoq;
        $hasPaid = $this->paymentPlans->contains(
            fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::Paid
        );
        $hasReady = $this->paymentPlans->contains(
            fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::ReadyToPay
        );
        $hasPlan = $this->paymentPlans->isNotEmpty();

        $projectState = match ($this->project->status) {
            ProjectStatus::Draft => 'current',
            ProjectStatus::Active, ProjectStatus::Completed => 'done',
        };

        if ($boq === null) {
            $boqState = 'todo';
            $boqDetail = null;
            $approvalState = 'todo';
            $approvalDetail = null;
        } else {
            $boqState = match ($boq->status) {
                BoqStatus::Draft => 'current',
                BoqStatus::WaitingApproval,
                BoqStatus::RevisionRequired,
                BoqStatus::Approved => 'done',
            };
            $boqDetail = $boq->status === BoqStatus::Draft ? 'DRAFT' : null;

            $approvalState = match ($boq->status) {
                BoqStatus::Draft => 'todo',
                BoqStatus::WaitingApproval => 'current',
                BoqStatus::RevisionRequired => 'current',
                BoqStatus::Approved => 'done',
            };
            $approvalDetail = match ($boq->status) {
                BoqStatus::WaitingApproval => 'WAITING APPROVAL',
                BoqStatus::RevisionRequired => 'REVISION REQUIRED',
                BoqStatus::Approved => 'APPROVED',
                default => null,
            };
        }

        if ($boq === null || $boq->status !== BoqStatus::Approved) {
            $paymentState = 'todo';
            $paymentDetail = null;
        } elseif ($hasPaid && ! $hasReady && $this->paymentPlans->every(
            fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::Paid
        )) {
            $paymentState = 'done';
            $paymentDetail = 'PAID';
        } else {
            $paymentState = 'current';
            $paymentDetail = $hasReady ? 'READY TO PAY' : ($hasPlan ? 'PLANNED' : 'PENDING');
        }

        if ($boq === null || ($boq->status !== BoqStatus::Approved && $this->overallProgress <= 0)) {
            $progressState = 'todo';
            $progressDetail = null;
        } elseif ($this->overallProgress >= 100) {
            $progressState = 'done';
            $progressDetail = '100%';
        } else {
            $progressState = 'current';
            $progressDetail = number_format($this->overallProgress, 1).'%';
        }

        return [
            ['key' => 'project', 'label' => 'PROJECT', 'state' => $projectState, 'detail' => $this->project->status->value],
            ['key' => 'boq', 'label' => 'BOQ', 'state' => $boqState, 'detail' => $boqDetail],
            ['key' => 'approval', 'label' => 'APPROVAL', 'state' => $approvalState, 'detail' => $approvalDetail],
            ['key' => 'payment', 'label' => 'PAYMENT', 'state' => $paymentState, 'detail' => $paymentDetail],
            ['key' => 'progress', 'label' => 'PROGRESS', 'state' => $progressState, 'detail' => $progressDetail],
        ];
    }

    /**
     * @return array{key: string, message: string, button: string, url: string|null}
     */
    private function buildNextAction(?User $user): array
    {
        $action = $this->resolveNextAction();

        if ($user === null || $this->userCanPerformAction($user, $action)) {
            return $action;
        }

        return [
            'key' => $action['key'],
            'message' => $action['message'].' Tindakan ini menunggu role yang berwenang.',
            'button' => '',
            'url' => null,
        ];
    }

    /**
     * @return array{key: string, message: string, button: string, url: string|null}
     */
    private function resolveNextAction(): array
    {
        if ($this->overallProgress >= 100 && $this->totalBoqValue > 0) {
            return [
                'key' => 'completed',
                'message' => 'Project telah mencapai 100%.',
                'button' => 'VIEW REPORT',
                'url' => route('projects.progress.index', $this->project),
            ];
        }

        if ($this->primaryBoq === null) {
            return [
                'key' => 'create_boq',
                'message' => 'Project belum memiliki BOQ.',
                'button' => 'CREATE BOQ',
                'url' => route('projects.boq.create', $this->project),
            ];
        }

        $boq = $this->primaryBoq;

        return match ($boq->status) {
            BoqStatus::Draft => [
                'key' => 'open_boq',
                'message' => 'BOQ belum disubmit.',
                'button' => 'OPEN BOQ',
                'url' => route('boqs.show', $boq),
            ],
            BoqStatus::WaitingApproval => [
                'key' => 'review_boq',
                'message' => 'BOQ menunggu review.',
                'button' => 'REVIEW BOQ',
                'url' => route('boqs.review', $boq),
            ],
            BoqStatus::RevisionRequired => [
                'key' => 'open_revision',
                'message' => 'BOQ membutuhkan revisi.',
                'button' => 'OPEN REVISION',
                'url' => route('boqs.revision.show', $boq),
            ],
            BoqStatus::Approved => $this->nextActionForApprovedBoq($boq),
        };
    }

    /**
     * @param  array{key: string, message: string, button: string, url: string|null}  $action
     */
    private function userCanPerformAction(User $user, array $action): bool
    {
        return match ($action['key']) {
            'completed' => $user->hasPermission(Permission::ProgressView),
            'create_boq' => $user->can('create', Boq::class),
            'open_boq' => $this->primaryBoq !== null && (
                $user->can('update', $this->primaryBoq) || $user->can('submit', $this->primaryBoq)
            ),
            'review_boq' => $this->primaryBoq !== null && $user->can('review', $this->primaryBoq),
            'open_revision' => $this->primaryBoq !== null && (
                $user->can('prepare', [BoqRevision::class, $this->primaryBoq])
                || $user->can('update', [BoqRevision::class, $this->primaryBoq])
            ),
            'process_payment' => $this->readyPaymentPlan() !== null
                && $user->can('process', [Payment::class, $this->readyPaymentPlan()]),
            'update_progress' => $user->hasPermission(Permission::ProgressUpdate),
            'payment_planning' => $user->can('create', [PaymentPlan::class, $this->project]),
            default => true,
        };
    }

    private function readyPaymentPlan(): ?PaymentPlan
    {
        return $this->paymentPlans->first(
            fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::ReadyToPay
        );
    }

    /**
     * @return array{key: string, message: string, button: string, url: string|null}
     */
    private function nextActionForApprovedBoq(Boq $boq): array
    {
        $readyPlan = $this->readyPaymentPlan();

        if ($readyPlan !== null) {
            return [
                'key' => 'process_payment',
                'message' => 'Payment siap diproses.',
                'button' => 'PROCESS PAYMENT',
                'url' => route('payments.pay', $readyPlan),
            ];
        }

        $hasPaid = $this->paymentPlans->contains(
            fn (PaymentPlan $plan) => $plan->status === PaymentPlanStatus::Paid
        );

        if ($hasPaid) {
            return [
                'key' => 'update_progress',
                'message' => 'Update progress item pekerjaan.',
                'button' => 'UPDATE PROGRESS',
                'url' => route('projects.progress.index', $this->project),
            ];
        }

        return [
            'key' => 'payment_planning',
            'message' => 'BOQ telah disetujui. Buat payment plan.',
            'button' => 'PAYMENT PLANNING',
            'url' => route('projects.payment.index', $this->project),
        ];
    }
}
