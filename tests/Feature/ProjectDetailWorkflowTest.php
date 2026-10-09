<?php

namespace Tests\Feature;

use App\Actions\BOQ\RequestRevision;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Progress\UpdateItemProgress;
use App\Enums\PaymentPlanType;
use App\Enums\ProjectStatus;
use App\Support\ProjectDetailContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectDetailWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_next_action_create_boq_when_project_has_none(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA1']);

        $context = ProjectDetailContext::for($project);

        $this->assertSame('create_boq', $context->nextAction['key']);
        $this->assertSame('CREATE BOQ', $context->nextAction['button']);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Project belum memiliki BOQ.')
            ->assertSee('CREATE BOQ');
    }

    public function test_next_action_open_boq_when_draft(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA2']);
        $this->makeDraftBoq($user, $project, code: 'BOQ-PRJNA2');

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame('open_boq', $context->nextAction['key']);
        $this->assertSame('OPEN BOQ', $context->nextAction['button']);
        $this->assertSame('current', collect($context->workflowSteps)->firstWhere('key', 'boq')['state']);
    }

    public function test_next_action_review_when_waiting_approval(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA3']);
        $boq = $this->makeDraftBoq($user, $project, code: 'BOQ-PRJNA3');
        app(SubmitBOQ::class)->handle($user, $boq);

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame('review_boq', $context->nextAction['key']);
        $this->assertSame('REVIEW BOQ', $context->nextAction['button']);
    }

    public function test_next_action_open_revision_when_revision_required(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA4']);
        $boq = $this->makeDraftBoq($user, $project, code: 'BOQ-PRJNA4');
        app(SubmitBOQ::class)->handle($user, $boq);
        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();
        app(RequestRevision::class)->handle($user, $boq->fresh(), [
            'reason' => 'Harga perlu disesuaikan.',
            'item_ids' => [$item->id],
        ]);

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame('open_revision', $context->nextAction['key']);
        $this->assertSame('OPEN REVISION', $context->nextAction['button']);
    }

    public function test_next_action_payment_planning_when_approved_without_payment(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA5']);
        $this->makeApprovedBoq($user, $project, 'BOQ-PRJNA5');

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame('payment_planning', $context->nextAction['key']);
        $this->assertSame('PAYMENT PLANNING', $context->nextAction['button']);
        $this->assertSame('done', collect($context->workflowSteps)->firstWhere('key', 'approval')['state']);
        $this->assertSame('current', collect($context->workflowSteps)->firstWhere('key', 'payment')['state']);
    }

    public function test_next_action_process_payment_when_ready_to_pay(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA6']);
        $boq = $this->makeApprovedBoq($user, $project, 'BOQ-PRJNA6');

        app(CreatePaymentPlan::class)->handle($user, $boq, [
            'reference_code' => 'PP-NA6',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq, 1000000),
        ]);

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame('process_payment', $context->nextAction['key']);
        $this->assertSame('PROCESS PAYMENT', $context->nextAction['button']);
    }

    public function test_next_action_update_progress_when_paid(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA7']);
        $boq = $this->makeApprovedBoq($user, $project, 'BOQ-PRJNA7');
        $this->makePaidPlanForBoq($user, $boq, 'PP-NA7', 1000000);

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame('update_progress', $context->nextAction['key']);
        $this->assertSame('UPDATE PROGRESS', $context->nextAction['button']);
        $this->assertSame('done', collect($context->workflowSteps)->firstWhere('key', 'payment')['state']);
        $this->assertSame('current', collect($context->workflowSteps)->firstWhere('key', 'progress')['state']);
    }

    public function test_project_becomes_completed_when_progress_reaches_one_hundred(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-NA8']);
        $boq = $this->makeApprovedBoq($user, $project, 'BOQ-PRJNA8');
        $this->makePaidPlanForBoq($user, $boq, 'PP-NA8', 1000000);

        foreach ($boq->fresh('cogs.items')->cogs as $cogs) {
            foreach ($cogs->items as $item) {
                app(UpdateItemProgress::class)->handle($user, $item, 100);
            }
        }

        $project->refresh();
        $context = ProjectDetailContext::for($project);

        $this->assertSame(ProjectStatus::Completed, $project->status);
        $this->assertSame(100.0, $context->overallProgress);
        $this->assertSame('completed', $context->nextAction['key']);
        $this->assertSame('VIEW REPORT', $context->nextAction['button']);
        $this->assertSame('done', collect($context->workflowSteps)->firstWhere('key', 'progress')['state']);
    }

    public function test_project_detail_tabs_render_with_eager_loaded_context(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-TAB']);
        $boq = $this->makeApprovedBoq($user, $project, 'BOQ-PRJTAB');
        $this->makePaidPlanForBoq($user, $boq, 'PP-TAB', 2000000);

        $this->actingAs($user)->get(route('projects.show', $project))->assertOk()->assertSee('Next Action');
        $this->actingAs($user)->get(route('projects.boq.index', $project))->assertOk()->assertSee($boq->code);
        $this->actingAs($user)->get(route('projects.payment.index', $project))->assertOk()->assertSee('Sisa BOQ');
        $this->actingAs($user)->get(route('projects.progress.index', $project))->assertOk()->assertSee('Overall Project Progress');
        $this->actingAs($user)->get(route('projects.edit', $project))->assertOk()->assertSee('Edit Project');
    }

    public function test_summary_cards_use_business_logic_values(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-SUM']);
        $boq = $this->makeApprovedBoq($user, $project, 'BOQ-PRJSUM');
        $this->makePaidPlanForBoq($user, $boq, 'PP-SUM', 5000000);

        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();
        app(UpdateItemProgress::class)->handle($user, $item, 50);

        $context = ProjectDetailContext::for($project->fresh());

        $this->assertSame($project->fresh()->totalCost(), $context->totalBoqValue);
        $this->assertSame($project->fresh()->earnedValue(), $context->earnedValue);
        $this->assertSame(5000000.0, $context->totalPaid);
        $this->assertGreaterThan(0, $context->totalCogs);
    }
}
