<?php

namespace Tests\Feature;

use App\Actions\BOQ\ConfirmBOQ;
use App\Actions\BOQ\PrepareBoqForRevision;
use App\Actions\BOQ\RequestRevision;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\BOQ\SubmitRevision;
use App\Actions\BOQ\UpdateBoqItem;
use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Payment\ProcessPayment;
use App\Actions\Progress\UpdateItemProgress;
use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Enums\PaymentStatus;
use App\Enums\RevisionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class AcceptanceWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_scenario_one_happy_path_from_project_to_progress(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);

        app(SubmitBOQ::class)->handle($actor, $boq);
        app(ConfirmBOQ::class)->handle($actor, $boq->fresh(), 'Confirmed');

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);

        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq->fresh(), [
            'reference_code' => 'PP-001',
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq->fresh(), 5000000),
        ]);

        app(ProcessPayment::class)->handle($actor, $plan, [
            'payment_code' => 'PAY-001',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
            'payment_method' => 'bank_transfer',
        ]);

        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $plan->fresh()->payments->first()->status);

        $item = $boq->fresh('cogs.items')->cogs->first()->items->firstWhere('code', 'ITEM-E01');
        app(UpdateItemProgress::class)->handle($actor, $item, 100);

        $this->assertGreaterThan(0, $project->fresh()->progressPercentage());
        $this->assertSame('100.00', (string) $item->fresh()->progress_percentage);
    }

    public function test_scenario_two_revision_then_confirm(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-200']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-PRJ200');

        app(SubmitBOQ::class)->handle($actor, $boq);

        $designDrawing = $boq->fresh('cogs.items')->cogs->first()->items->firstWhere('code', 'ITEM-E01');

        $revision = app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => 'Harga perlu disesuaikan.',
            'item_ids' => [$designDrawing->id],
            'item_reasons' => [
                $designDrawing->id => 'Harga perlu disesuaikan.',
            ],
        ]);

        $this->assertSame('BOQ-PRJ200-REV01', $revision->revision_code);
        $this->assertSame(BoqStatus::RevisionRequired, $boq->fresh()->status);

        app(PrepareBoqForRevision::class)->handle($actor, $boq->fresh());
        app(UpdateBoqItem::class)->handle($actor, $designDrawing->fresh(), [
            'unit_price' => 2200000,
        ]);
        app(SubmitRevision::class)->handle($actor, $boq->fresh());
        app(ConfirmBOQ::class)->handle($actor, $boq->fresh(), 'Approved after revision');

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);
        $this->assertDatabaseHas('boq_revisions', [
            'id' => $revision->id,
            'revision_code' => 'BOQ-PRJ200-REV01',
            'status' => RevisionStatus::Resolved->value,
        ]);
        $this->assertSame('2200000.00', (string) $designDrawing->fresh()->unit_price);
    }
}
