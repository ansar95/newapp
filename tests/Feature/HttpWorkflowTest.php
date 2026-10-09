<?php

namespace Tests\Feature;

use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class HttpWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_dashboard_and_projects(): void
    {
        $user = $this->makeSuperadmin();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Projects');

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('New Project');
    }

    public function test_user_can_create_project_via_http(): void
    {
        $user = $this->makeSuperadmin();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'code' => 'PRJ-HTTP',
            'name' => 'HTTP Project',
            'client_name' => 'PT HTTP',
            'description' => 'Created via HTTP',
            'start_date' => now()->toDateString(),
            'target_end_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', ['code' => 'PRJ-HTTP']);
    }

    public function test_full_http_happy_path_through_progress(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-UI']);
        $boq = $this->makeDraftBoq($user, $project, code: 'BOQ-PRJUI');

        $this->actingAs($user)
            ->post(route('boqs.submit', $boq))
            ->assertRedirect(route('boqs.show', $boq));

        $this->assertSame(BoqStatus::WaitingApproval, $boq->fresh()->status);

        $this->actingAs($user)
            ->get(route('boqs.review', $boq))
            ->assertOk()
            ->assertSee('CONFIRM BOQ');

        $this->actingAs($user)
            ->post(route('boqs.confirm', $boq), ['review_notes' => 'OK'])
            ->assertRedirect(route('boqs.show', $boq));

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);

        $approvedBoq = $boq->fresh('cogs.items');
        $itemIds = $this->boqItemIdsForAmount($approvedBoq, 5000000);

        $this->actingAs($user)
            ->post(route('payment-plans.store', $project), [
                'reference_code' => 'PP-UI-001',
                'type' => PaymentPlanType::ReadyToPay->value,
                'item_ids' => $itemIds,
            ])
            ->assertRedirect();

        $plan = $boq->fresh()->paymentPlans()->first();
        $this->assertSame(PaymentPlanStatus::ReadyToPay, $plan->status);

        $this->actingAs($user)
            ->post(route('payments.store', $plan), [
                'payment_code' => 'PAY-UI-001',
                'payment_date' => now()->toDateString(),
                'amount' => $plan->amount,
                'payment_method' => 'bank_transfer',
            ])
            ->assertRedirect(route('projects.payment.index', $project));

        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);

        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();

        $this->actingAs($user)
            ->patch(route('progress.update', $item), [
                'progress_percentage' => 40,
            ])
            ->assertRedirect();

        $this->assertSame('40.00', (string) $item->fresh()->progress_percentage);

        $this->actingAs($user)
            ->get(route('projects.progress.index', $project))
            ->assertOk()
            ->assertSee('Overall Project Progress');
    }

    public function test_http_revision_flow(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-REV']);
        $boq = $this->makeDraftBoq($user, $project, code: 'BOQ-PRJREV');

        $this->actingAs($user)->post(route('boqs.submit', $boq));

        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();

        $this->actingAs($user)
            ->post(route('boqs.request-revision', $boq), [
                'reason' => 'Harga perlu disesuaikan.',
                'item_ids' => [$item->id],
            ])
            ->assertRedirect(route('boqs.revision.show', $boq));

        $this->assertSame(BoqStatus::RevisionRequired, $boq->fresh()->status);

        $this->actingAs($user)
            ->post(route('boqs.revision.prepare', $boq))
            ->assertRedirect();

        $this->actingAs($user)
            ->patch(route('boqs.revision.items.update', $item), [
                'quantity' => 1,
                'unit' => 'ls',
                'unit_price' => 2500000,
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('boqs.revision.submit', $boq))
            ->assertRedirect(route('boqs.show', $boq));

        $this->assertSame(BoqStatus::WaitingApproval, $boq->fresh()->status);

        $this->actingAs($user)
            ->post(route('boqs.confirm', $boq), ['review_notes' => 'Approved after revision'])
            ->assertRedirect();

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);
        $this->assertDatabaseHas('boq_revisions', [
            'boq_id' => $boq->id,
            'revision_code' => 'BOQ-PRJREV-REV01',
        ]);
    }

    public function test_http_can_start_revision_from_approved_boq_page(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-REV2']);
        $boq = $this->makeApprovedBoq($user, $project, code: 'BOQ-PRJREV2');
        $item = $boq->cogs->first()->items->first();

        $this->actingAs($user)
            ->get(route('projects.boq.index', $project))
            ->assertOk()
            ->assertSee('Revisi BOQ');

        $this->actingAs($user)
            ->post(route('boqs.request-revision', $boq), [
                'reason' => 'Penyesuaian setelah approval.',
                'item_ids' => [$item->id],
            ])
            ->assertRedirect(route('boqs.revision.show', $boq));

        $this->assertSame(BoqStatus::RevisionRequired, $boq->fresh()->status);

        $this->actingAs($user)->post(route('boqs.revision.prepare', $boq));
        $this->actingAs($user)->post(route('boqs.revision.submit', $boq));

        $this->assertSame(BoqStatus::WaitingApproval, $boq->fresh()->status);
    }
}
