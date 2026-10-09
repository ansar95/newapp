<?php

namespace Tests\Feature;

use App\Actions\Payment\CreatePaymentPlan;
use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class AuthorizationStatusTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_approver_cannot_approve_draft_boq(): void
    {
        $admin = $this->makeSuperadmin();
        $approver = $this->makeApprover();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-ST1']);
        $boq = $this->makeDraftBoq($admin, $project, code: 'BOQ-ST1');

        $this->assertSame(BoqStatus::Draft, $boq->status);

        $this->actingAs($approver)
            ->post(route('boqs.confirm', $boq), ['review_notes' => 'Too early'])
            ->assertForbidden();

        $this->assertSame(BoqStatus::Draft, $boq->fresh()->status);
    }

    public function test_approver_can_approve_waiting_approval_boq(): void
    {
        $admin = $this->makeSuperadmin();
        $approver = $this->makeApprover();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-ST2']);
        $boq = $this->makeDraftBoq($admin, $project, code: 'BOQ-ST2');
        $this->actingAs($admin)->post(route('boqs.submit', $boq));

        $this->assertSame(BoqStatus::WaitingApproval, $boq->fresh()->status);

        $this->actingAs($approver)
            ->post(route('boqs.confirm', $boq), ['review_notes' => 'Approved'])
            ->assertRedirect();

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);
    }

    public function test_finance_cannot_pay_already_paid_plan(): void
    {
        $admin = $this->makeSuperadmin();
        $finance = $this->makeFinance();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-ST3']);
        $boq = $this->makeApprovedBoq($admin, $project, code: 'BOQ-ST3');
        $plan = $this->makePaidPlanForBoq($admin, $boq, 'PP-ST3', 3000000);

        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);

        $this->actingAs($finance)
            ->post(route('payments.store', $plan), [
                'payment_code' => 'PAY-ST3-AGAIN',
                'payment_date' => now()->toDateString(),
                'amount' => 3000000,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TRX-ST3',
            ])
            ->assertForbidden();
    }

    public function test_project_site_can_update_progress_but_not_unit_price(): void
    {
        $admin = $this->makeSuperadmin();
        $site = $this->makeProjectSite();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-ST4']);
        $boq = $this->makeApprovedBoq($admin, $project, code: 'BOQ-ST4');
        $this->makePaidPlanForBoq($admin, $boq, 'PP-ST4', 5000000);
        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();
        $originalPrice = (float) $item->unit_price;

        $this->actingAs($site)->patch(route('progress.update', $item), [
            'progress_percentage' => 55,
        ])->assertRedirect();

        $this->assertEquals(55.0, (float) $item->fresh()->progress_percentage);

        $this->actingAs($site)->patch(route('boqs.items.update', $item), [
            'name' => $item->name,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => $originalPrice + 1000,
        ])->assertForbidden();

        $this->assertEquals($originalPrice, (float) $item->fresh()->unit_price);
    }

    public function test_finance_cannot_process_planned_payment_until_ready(): void
    {
        $admin = $this->makeSuperadmin();
        $finance = $this->makeFinance();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-ST5']);
        $boq = $this->makeApprovedBoq($admin, $project, code: 'BOQ-ST5');

        $plan = app(CreatePaymentPlan::class)->handle($admin, $boq, [
            'reference_code' => 'PP-ST5',
            'type' => PaymentPlanType::Planned->value,
            'item_ids' => $this->boqItemIdsForAmount($boq, 1000000),
            'planned_date' => now()->addWeek()->toDateString(),
        ]);

        $this->assertSame(PaymentPlanStatus::Planned, $plan->status);

        $this->actingAs($finance)
            ->get(route('payments.pay', $plan))
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('payment-plans.ready', $plan))
            ->assertRedirect();

        $this->assertSame(PaymentPlanStatus::ReadyToPay, $plan->fresh()->status);

        $this->actingAs($finance)
            ->get(route('payments.pay', $plan))
            ->assertOk();
    }
}
