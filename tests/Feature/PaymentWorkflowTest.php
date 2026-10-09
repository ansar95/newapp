<?php

namespace Tests\Feature;

use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Payment\MarkPaymentPlanReady;
use App\Actions\Payment\ProcessPayment;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_cannot_create_payment_plan_when_boq_not_approved(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);
        $itemId = $boq->fresh('cogs.items')->cogs->first()->items->first()->id;

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('BOQ belum APPROVED');

        app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-001',
            'type' => PaymentPlanType::Planned->value,
            'planned_date' => '2026-09-30',
            'item_ids' => [$itemId],
        ]);
    }

    public function test_create_planned_payment_plan_from_selected_items(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $itemIds = $this->boqItemIdsForAmount($boq, 5000000);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-001',
            'type' => PaymentPlanType::Planned->value,
            'planned_date' => '2026-09-30',
            'item_ids' => $itemIds,
        ]);

        $this->assertSame(PaymentPlanType::Planned, $plan->type);
        $this->assertSame(PaymentPlanStatus::Planned, $plan->status);
        $this->assertSame('5000000.00', (string) $plan->amount);
        $this->assertCount(1, $plan->items);
        $this->assertDatabaseHas('payment_plan_items', [
            'payment_plan_id' => $plan->id,
            'boq_item_id' => $itemIds[0],
        ]);
    }

    public function test_create_ready_to_pay_payment_plan(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-002',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq, 10000000),
        ]);

        $this->assertSame(PaymentPlanType::ReadyToPay, $plan->type);
        $this->assertSame(PaymentPlanStatus::ReadyToPay, $plan->status);
        $this->assertSame('10000000.00', (string) $plan->amount);
    }

    public function test_same_item_can_be_planned_in_multiple_plans(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $itemIds = $this->boqItemIdsForAmount($boq, 5000000);

        app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-DUP-1',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $itemIds,
        ]);

        $second = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-DUP-2',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $itemIds,
        ]);

        $this->assertSame('5000000.00', (string) $second->amount);
        $this->assertSame(2, $boq->paymentPlans()->count());
    }

    public function test_process_payment_marks_plan_paid(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-003',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq, 5000000),
        ]);

        $payment = app(ProcessPayment::class)->handle($actor, $plan, [
            'payment_code' => 'PAY-003',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-003',
        ]);

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);
        $this->assertSame($actor->id, $payment->paid_by);
        $this->assertNotNull($payment->payment_date);
    }

    public function test_planned_plan_must_be_marked_ready_before_payment(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-004',
            'type' => PaymentPlanType::Planned->value,
            'planned_date' => '2026-09-30',
            'item_ids' => $this->boqItemIdsForAmount($boq, 5000000),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('READY_TO_PAY');

        app(ProcessPayment::class)->handle($actor, $plan, [
            'payment_code' => 'PAY-004',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
        ]);
    }

    public function test_mark_planned_plan_ready_then_process_payment(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-005',
            'type' => PaymentPlanType::Planned->value,
            'planned_date' => '2026-09-30',
            'item_ids' => $this->boqItemIdsForAmount($boq, 5000000),
        ]);

        app(MarkPaymentPlanReady::class)->handle($actor, $plan);

        $payment = app(ProcessPayment::class)->handle($actor, $plan->fresh(), [
            'payment_code' => 'PAY-005',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
        ]);

        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
    }

    public function test_cannot_pay_same_plan_twice(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $plan = $this->makePaidPlanForBoq($actor, $boq, 'PP-006', 5000000);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('tidak boleh dibayar dua kali');

        app(ProcessPayment::class)->handle($actor, $plan->fresh(), [
            'payment_code' => 'PAY-006B',
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
        ]);
    }

    public function test_payment_different_from_plan_amount_must_be_allocated(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => 'PP-007',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq, 5000000),
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Alokasikan pembayaran ke item BOQ');

        app(ProcessPayment::class)->handle($actor, $plan, [
            'payment_code' => 'PAY-007',
            'payment_date' => now()->toDateString(),
            'amount' => ((float) $plan->amount) + 1,
        ]);
    }
}
