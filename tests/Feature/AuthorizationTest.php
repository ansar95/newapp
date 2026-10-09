<?php

namespace Tests\Feature;

use App\Actions\Payment\CreatePaymentPlan;
use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Boq;
use App\Models\PaymentPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_superadmin_can_perform_all_key_actions(): void
    {
        $user = $this->makeSuperadmin();
        $project = $this->makeActiveProject($user, ['code' => 'PRJ-SA']);
        $boq = $this->makeDraftBoq($user, $project, code: 'BOQ-SA');

        $this->actingAs($user)->post(route('projects.store'), [
            'code' => 'PRJ-SA2',
            'name' => 'Another',
            'client_name' => 'Client',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('boqs.submit', $boq))->assertRedirect();
        $this->assertSame(BoqStatus::WaitingApproval, $boq->fresh()->status);

        $this->actingAs($user)->get(route('boqs.review', $boq))->assertOk();
        $this->actingAs($user)->post(route('boqs.confirm', $boq), ['review_notes' => 'OK'])->assertRedirect();
        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);

        $this->actingAs($user)->post(route('payment-plans.store', $project), [
            'reference_code' => 'PP-SA',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq->fresh(), 1000000),
        ])->assertRedirect();

        $plan = PaymentPlan::query()->where('reference_code', 'PP-SA')->firstOrFail();
        $this->actingAs($user)->post(route('payments.store', $plan), [
            'payment_code' => 'PAY-SA',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-SA',
        ])->assertRedirect();

        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();
        $this->actingAs($user)->patch(route('progress.update', $item), [
            'progress_percentage' => 25,
        ])->assertRedirect();
    }

    public function test_project_admin_can_create_project_but_cannot_approve_boq(): void
    {
        $admin = $this->makeSuperadmin();
        $projectAdmin = $this->makeProjectAdmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-PA']);
        $boq = $this->makeDraftBoq($admin, $project, code: 'BOQ-PA');
        $this->actingAs($admin)->post(route('boqs.submit', $boq));

        $this->actingAs($projectAdmin)->post(route('projects.store'), [
            'code' => 'PRJ-PA-NEW',
            'name' => 'Project Admin Project',
            'client_name' => 'Client',
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', ['code' => 'PRJ-PA-NEW']);

        $this->actingAs($projectAdmin)
            ->post(route('boqs.confirm', $boq), ['review_notes' => 'Nope'])
            ->assertForbidden();
    }

    public function test_boq_cost_control_can_create_and_submit_but_cannot_approve(): void
    {
        $admin = $this->makeSuperadmin();
        $boqUser = $this->makeBoqCostControl();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-BC']);

        $this->actingAs($boqUser)->post(route('projects.boq.store', $project), [
            'code' => 'BOQ-BC',
            'revision_number' => 1,
            'cogs' => $this->defaultCogsPayload(),
        ])->assertRedirect();

        $boq = Boq::query()->where('code', 'BOQ-BC')->firstOrFail();

        $this->actingAs($boqUser)->post(route('boqs.submit', $boq))->assertRedirect();
        $this->assertSame(BoqStatus::WaitingApproval, $boq->fresh()->status);

        $this->actingAs($boqUser)
            ->post(route('boqs.confirm', $boq), ['review_notes' => 'Nope'])
            ->assertForbidden();
    }

    public function test_approver_can_review_and_approve_but_cannot_edit_boq(): void
    {
        $admin = $this->makeSuperadmin();
        $approver = $this->makeApprover();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-AP']);
        $boq = $this->makeDraftBoq($admin, $project, code: 'BOQ-AP');
        $item = $boq->cogs->first()->items->first();

        $this->actingAs($admin)->post(route('boqs.submit', $boq));

        $this->actingAs($approver)->get(route('boqs.review', $boq))->assertOk();
        $this->actingAs($approver)->post(route('boqs.confirm', $boq), ['review_notes' => 'OK'])->assertRedirect();
        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);

        $draft = $this->makeDraftBoq($admin, $project, code: 'BOQ-AP2');
        $draftItem = $draft->cogs->first()->items->first();

        $this->actingAs($approver)->patch(route('boqs.items.update', $draftItem), [
            'name' => $draftItem->name,
            'quantity' => $draftItem->quantity,
            'unit' => $draftItem->unit,
            'unit_price' => 999,
        ])->assertForbidden();
    }

    public function test_finance_can_manage_payment_but_cannot_approve_boq(): void
    {
        $admin = $this->makeSuperadmin();
        $finance = $this->makeFinance();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-FN']);
        $boq = $this->makeApprovedBoq($admin, $project, code: 'BOQ-FN');

        $this->actingAs($finance)->post(route('payment-plans.store', $project), [
            'reference_code' => 'PP-FN',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq->fresh(), 2000000),
        ])->assertRedirect();

        $plan = PaymentPlan::query()->where('reference_code', 'PP-FN')->firstOrFail();
        $this->assertSame(PaymentPlanStatus::ReadyToPay, $plan->status);

        $this->actingAs($finance)->post(route('payments.store', $plan), [
            'payment_code' => 'PAY-FN',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-FN',
        ])->assertRedirect();

        $waiting = $this->makeDraftBoq($admin, $project, code: 'BOQ-FN2');
        $this->actingAs($admin)->post(route('boqs.submit', $waiting));

        $this->actingAs($finance)
            ->post(route('boqs.confirm', $waiting), ['review_notes' => 'Nope'])
            ->assertForbidden();
    }

    public function test_project_site_can_view_boq_and_update_progress_but_not_edit_or_pay(): void
    {
        $admin = $this->makeSuperadmin();
        $site = $this->makeProjectSite();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-PS']);
        $boq = $this->makeApprovedBoq($admin, $project, code: 'BOQ-PS');
        $this->makePaidPlanForBoq($admin, $boq, 'PP-PS', 5000000);
        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();

        $this->actingAs($site)->get(route('boqs.show', $boq))->assertOk();

        $this->actingAs($site)->patch(route('progress.update', $item), [
            'progress_percentage' => 40,
        ])->assertRedirect();
        $this->assertEquals(40.0, (float) $item->fresh()->progress_percentage);

        $this->actingAs($site)->patch(route('boqs.items.update', $item), [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => 1,
        ])->assertForbidden();

        $readyPlan = app(CreatePaymentPlan::class)->handle($admin, $boq->fresh(), [
            'reference_code' => 'PP-PS2',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq->fresh(), 1000000),
        ]);

        $this->actingAs($site)->post(route('payments.store', $readyPlan), [
            'payment_code' => 'PAY-PS',
            'payment_date' => now()->toDateString(),
            'amount' => $readyPlan->amount,
            'payment_method' => 'cash',
            'reference_number' => 'TRX-PS',
        ])->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    public function test_role_matrix_permissions_match_expected_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = $this->makeUserWithRole($role);
            $this->assertTrue($user->hasPermission(Permission::ProjectView));
        }

        $this->assertFalse($this->makeApprover()->hasPermission(Permission::BoqUpdate));
        $this->assertFalse($this->makeFinance()->hasPermission(Permission::BoqApprove));
        $this->assertFalse($this->makeProjectSite()->hasPermission(Permission::PaymentProcess));
        $this->assertTrue($this->makeFinance()->hasPermission(Permission::PaymentProcess));
        $this->assertTrue($this->makeProjectSite()->hasPermission(Permission::ProgressUpdate));
    }
}
