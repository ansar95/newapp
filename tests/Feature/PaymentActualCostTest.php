<?php

namespace Tests\Feature;

use App\Actions\BOQ\RequestRevision;
use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Payment\PaymentAllocationMath;
use App\Actions\Payment\ProcessPayment;
use App\Actions\Payment\VoidPayment;
use App\Actions\Procurement\AllocateProcurementItem;
use App\Actions\Progress\UpdateItemProgress;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectActualCost;
use App\Support\ProjectDetailContext;
use App\Support\ReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class PaymentActualCostTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private Boq $boq;

    /**
     * @var Collection<string, BoqItem>
     */
    private Collection $items;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin);
        $this->boq = $this->makeApprovedBoq($this->admin, $this->project);
        $this->items = $this->boq->cogs->flatMap->items->keyBy('code');
    }

    public function test_plan_uses_free_planned_amount_per_item(): void
    {
        $plan = $this->makeReadyPlan('PP-001', ['ITEM-E01' => 2500000, 'ITEM-E02' => 1000000]);

        $this->assertSame('3500000.00', (string) $plan->amount);
        $this->assertSame(2500000.0, (float) $plan->items->firstWhere('code', 'ITEM-E01')->pivot->planned_amount);
    }

    public function test_plan_form_posts_selected_items_with_planned_amounts(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payment-plans.store', $this->project), [
                'reference_code' => 'PP-HTTP',
                'type' => PaymentPlanType::ReadyToPay->value,
                'items' => [
                    $this->items['ITEM-E01']->id => ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E01']->id, 'planned_amount' => '2750000'],
                    $this->items['ITEM-E02']->id => ['boq_item_id' => $this->items['ITEM-E02']->id, 'planned_amount' => '3000000'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = PaymentPlan::query()->where('reference_code', 'PP-HTTP')->sole();

        $this->assertSame('2750000.00', (string) $plan->amount);
        $this->assertSame([$this->items['ITEM-E01']->id], $plan->items->pluck('id')->all());
    }

    public function test_saved_plans_appear_in_payment_plan_list(): void
    {
        foreach ([
            ['PP-LIST-PLANNED', PaymentPlanType::Planned->value, now()->addWeek()->toDateString()],
            ['PP-LIST-READY', PaymentPlanType::ReadyToPay->value, null],
        ] as [$code, $type, $plannedDate]) {
            $this->actingAs($this->admin)
                ->post(route('payment-plans.store', $this->project), [
                    'reference_code' => $code,
                    'type' => $type,
                    'planned_date' => $plannedDate,
                    'items' => [
                        $this->items['ITEM-E01']->id => ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E01']->id, 'planned_amount' => '1000000'],
                    ],
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($this->admin)
            ->get(route('projects.payment.index', $this->project))
            ->assertOk()
            ->assertDontSee('Buat payment plan untuk memproses pembayaran.')
            ->assertSee('PP-LIST-PLANNED')
            ->assertSee('PP-LIST-READY');
    }

    public function test_payment_plans_stay_visible_read_only_while_boq_is_revised(): void
    {
        $this->makeReadyPlan('PP-REV-001', ['ITEM-E01' => 1000000]);

        app(RequestRevision::class)->handle($this->admin, $this->boq->fresh(), [
            'reason' => 'Harga perlu disesuaikan',
            'item_ids' => [$this->items['ITEM-E01']->id],
        ]);

        $this->actingAs($this->admin)
            ->get(route('projects.payment.index', $this->project))
            ->assertOk()
            ->assertSee('BOQ sedang direvisi.')
            ->assertSee('PP-REV-001')
            ->assertDontSee('Buat Rencana Pembayaran')
            ->assertDontSee('Process Payment');
    }

    public function test_payment_can_exceed_plan_and_boq_when_reason_is_given(): void
    {
        $plan = $this->makeReadyPlan('PP-002', ['ITEM-E01' => 2000000]);

        $payment = $this->pay($plan, 2600000, ['ITEM-E01' => 2600000], 'Kenaikan harga material');

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame('2600000.00', (string) $payment->amount);
        $this->assertSame('Kenaikan harga material', $payment->overrun_reason);
        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);

        $actual = ProjectActualCost::forProject($this->project);
        $this->assertSame(2600000.0, $actual->paymentForItem($this->items['ITEM-E01']));
        $this->assertSame(-600000.0, ProjectActualCost::variance((float) $this->items['ITEM-E01']->total_price, $actual->forItem($this->items['ITEM-E01'])));
        $this->assertSame(130.0, ProjectActualCost::percentage((float) $this->items['ITEM-E01']->total_price, $actual->forItem($this->items['ITEM-E01'])));
    }

    public function test_overrun_without_reason_is_rejected(): void
    {
        $plan = $this->makeReadyPlan('PP-003', ['ITEM-E01' => 2000000]);

        try {
            $this->pay($plan, 2000000.01, ['ITEM-E01' => 2000000.01]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('overrun_reason', $exception->errors());
            $this->assertStringContainsString('ITEM-E01', $exception->errors()['overrun_reason'][0]);
        }

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_overrun_check_includes_bmhp_actual(): void
    {
        $this->allocatePaidProcurement(ProcurementType::Bmhp, 'ITEM-E01', 1500000);

        $plan = $this->makeReadyPlan('PP-004', ['ITEM-E01' => 1000000]);

        $this->expectException(ValidationException::class);

        $this->pay($plan, 1000000, ['ITEM-E01' => 1000000]);
    }

    public function test_payment_within_boq_does_not_require_reason(): void
    {
        $plan = $this->makeReadyPlan('PP-005', ['ITEM-E01' => 1000000, 'ITEM-E02' => 1000000]);

        $payment = $this->pay($plan, 2500000, ['ITEM-E01' => 1500000, 'ITEM-E02' => 500000, 'ITEM-I01' => 500000]);

        $this->assertNull($payment->overrun_reason);
        $this->assertSame(3, $payment->allocations()->count());
    }

    public function test_allocation_total_must_equal_payment_amount(): void
    {
        $plan = $this->makeReadyPlan('PP-006', ['ITEM-E01' => 1000000]);

        try {
            $this->pay($plan, 1000000, ['ITEM-E01' => 900000]);
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(PaymentAllocationMath::MISMATCH_MESSAGE, $exception->getMessage());
        }

        $this->assertSame(PaymentPlanStatus::ReadyToPay, $plan->fresh()->status);
    }

    public function test_allocation_only_targets_items_of_the_plan_boq(): void
    {
        $otherProject = $this->makeActiveProject($this->admin, ['code' => 'PRJ-002', 'name' => 'Other']);
        $foreignItem = $this->makeApprovedBoq($this->admin, $otherProject, 'BOQ-PRJ002')->cogs->flatMap->items->first();
        $plan = $this->makeReadyPlan('PP-007', ['ITEM-E01' => 1000000]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('BOQ payment plan ini');

        app(ProcessPayment::class)->handle($this->admin, $plan, [
            'payment_code' => 'PAY-007',
            'payment_date' => now()->toDateString(),
            'amount' => 1000000,
            'allocations' => [['boq_item_id' => $foreignItem->id, 'allocated_amount' => 1000000]],
        ]);
    }

    public function test_pay_form_allocates_selected_rows_only(): void
    {
        $plan = $this->makeReadyPlan('PP-008', ['ITEM-E01' => 1000000]);

        $this->actingAs($this->admin)
            ->get(route('payments.pay', $plan))
            ->assertOk()
            ->assertSee('Alokasi ke Item BOQ');

        $this->actingAs($this->admin)
            ->post(route('payments.store', $plan), [
                'payment_code' => 'PAY-008',
                'payment_date' => now()->toDateString(),
                'amount' => '1200000',
                'allocations' => [
                    $this->items['ITEM-E01']->id => ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => '1000000'],
                    $this->items['ITEM-E02']->id => ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E02']->id, 'allocated_amount' => '200000'],
                    $this->items['ITEM-E03']->id => ['boq_item_id' => $this->items['ITEM-E03']->id, 'allocated_amount' => '999'],
                ],
            ])
            ->assertRedirect(route('projects.payment.index', $this->project))
            ->assertSessionHasNoErrors();

        $payment = Payment::query()->sole();
        $this->assertSame(
            [$this->items['ITEM-E01']->id => '1000000.00', $this->items['ITEM-E02']->id => '200000.00'],
            $payment->allocations->pluck('allocated_amount', 'boq_item_id')->all()
        );
    }

    public function test_overrun_check_includes_rental_actual(): void
    {
        $this->allocatePaidProcurement(ProcurementType::Rental, 'ITEM-E01', 1500000);

        $plan = $this->makeReadyPlan('PP-004', ['ITEM-E01' => 1000000]);

        $this->expectException(ValidationException::class);

        $this->pay($plan, 1000000, ['ITEM-E01' => 1000000]);
    }

    public function test_project_actual_is_bmhp_plus_rental_plus_payment_and_paid_uses_real_amount(): void
    {
        $this->allocatePaidProcurement(ProcurementType::Bmhp, 'ITEM-E03', 500000);
        $this->allocatePaidProcurement(ProcurementType::Rental, 'ITEM-E03', 300000);

        $plan = $this->makeReadyPlan('PP-009', ['ITEM-E02' => 3000000]);
        $this->pay($plan, 3200000, ['ITEM-E02' => 3200000], 'Pekerjaan tambah');

        $context = ProjectDetailContext::for($this->project->fresh(), $this->admin);

        $this->assertSame(500000.0, $context->bmhpActual);
        $this->assertSame(300000.0, $context->rentalActual);
        $this->assertSame(3200000.0, $context->paymentActual);
        $this->assertSame(4000000.0, $context->totalActual);
        $this->assertSame(7000000.0, $context->actualVariance);
        $this->assertSame(3200000.0, $context->totalPaid);
        $this->assertSame(3200000.0, app(ReportData::class)->hubSummary()['total_paid']);

        $this->actingAs($this->admin)
            ->get(route('projects.payment.index', $this->project))
            ->assertOk()
            ->assertSee('Pekerjaan tambah')
            ->assertSee('Rp4.000.000');
    }

    public function test_void_payment_is_removed_from_actual_and_plan_can_be_paid_again(): void
    {
        $plan = $this->makeReadyPlan('PP-010', ['ITEM-E01' => 1000000]);
        $payment = $this->pay($plan, 1000000, ['ITEM-E01' => 1000000]);

        $this->actingAs($this->admin)
            ->post(route('payments.void', $payment), ['void_reason' => ''])
            ->assertSessionHasErrors('void_reason');

        $this->actingAs($this->admin)
            ->post(route('payments.void', $payment), ['void_reason' => 'Salah nominal'])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Void, $payment->status);
        $this->assertSame('Salah nominal', $payment->void_reason);
        $this->assertSame($this->admin->id, $payment->voided_by);
        $this->assertSame(1, $payment->allocations()->count());
        $this->assertSame(PaymentPlanStatus::ReadyToPay, $plan->fresh()->status);
        $this->assertSame(0.0, ProjectActualCost::forProject($this->project)->paymentActual());

        $this->pay($plan->fresh(), 1100000, ['ITEM-E01' => 1100000], 'Koreksi nominal');

        $this->assertSame(1100000.0, ProjectActualCost::forProject($this->project)->paymentActual());
        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);
    }

    public function test_void_requires_payment_process_permission(): void
    {
        $plan = $this->makeReadyPlan('PP-011', ['ITEM-E01' => 1000000]);
        $payment = $this->pay($plan, 1000000, ['ITEM-E01' => 1000000]);

        $this->actingAs($this->makeApprover())
            ->post(route('payments.void', $payment), ['void_reason' => 'Coba batal'])
            ->assertForbidden();

        $this->actingAs($this->makeFinance())
            ->post(route('payments.void', $payment), ['void_reason' => 'Batal oleh finance'])
            ->assertRedirect();

        $this->actingAs($this->makeFinance())
            ->post(route('payments.void', $payment), ['void_reason' => 'Batal lagi'])
            ->assertForbidden();
    }

    public function test_progress_does_not_depend_on_payment_status(): void
    {
        $plan = $this->makeReadyPlan('PP-012', ['ITEM-E01' => 2000000]);
        $payment = $this->pay($plan, 2000000, ['ITEM-E01' => 2000000]);

        app(UpdateItemProgress::class)->handle($this->admin, $this->items['ITEM-E01']->fresh(), 30);

        app(VoidPayment::class)->handle($this->admin, $payment, ['void_reason' => 'Batal']);

        $item = app(UpdateItemProgress::class)->handle($this->admin, $this->items['ITEM-E01']->fresh(), 50);

        $this->assertSame('50.00', (string) $item->progress_percentage);
    }

    public function test_payment_does_not_change_boq_baseline(): void
    {
        $before = BoqItem::query()->orderBy('id')->get(['id', 'quantity', 'unit_price', 'total_price'])->toArray();

        $plan = $this->makeReadyPlan('PP-013', ['ITEM-E01' => 5000000]);
        $this->pay($plan, 5000000, ['ITEM-E01' => 5000000], 'Overrun');

        $this->assertSame($before, BoqItem::query()->orderBy('id')->get(['id', 'quantity', 'unit_price', 'total_price'])->toArray());
        $this->assertSame(11000000.0, $this->project->fresh()->totalCost());
    }

    /**
     * @param  array<string, float|int>  $plannedByCode
     */
    private function makeReadyPlan(string $reference, array $plannedByCode): PaymentPlan
    {
        return app(CreatePaymentPlan::class)->handle($this->admin, $this->boq, [
            'reference_code' => $reference,
            'type' => PaymentPlanType::ReadyToPay->value,
            'items' => collect($plannedByCode)
                ->map(fn ($amount, string $code) => ['boq_item_id' => $this->items[$code]->id, 'planned_amount' => $amount])
                ->values()
                ->all(),
        ]);
    }

    /**
     * @param  array<string, float|int>  $allocationsByCode
     */
    private function pay(PaymentPlan $plan, float|int $amount, array $allocationsByCode, ?string $overrunReason = null): Payment
    {
        return app(ProcessPayment::class)->handle($this->admin, $plan, [
            'payment_code' => 'PAY-'.$plan->reference_code.'-'.Payment::query()->count(),
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'overrun_reason' => $overrunReason,
            'allocations' => collect($allocationsByCode)
                ->map(fn ($allocated, string $code) => ['boq_item_id' => $this->items[$code]->id, 'allocated_amount' => $allocated])
                ->values()
                ->all(),
        ]);
    }

    private function allocatePaidProcurement(ProcurementType $type, string $boqItemCode, float|int $amount): void
    {
        $request = $this->makePaidProcurementRequest($this->admin, $this->project, $type, [
            $this->procurementItemPayload($type, 1, $amount),
        ]);

        app(AllocateProcurementItem::class)->handle($this->admin, $request->items->sole(), [
            ['boq_item_id' => $this->items[$boqItemCode]->id, 'allocated_amount' => $amount],
        ]);
    }
}
