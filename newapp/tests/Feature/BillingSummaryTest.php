<?php

namespace Tests\Feature;

use App\Actions\Billing\CancelClientBilling;
use App\Actions\Progress\UpdateItemProgress;
use App\Models\BoqItem;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectBillingSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BillingSummaryTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin, ['contract_value' => 1_000_000_000]);
    }

    public function test_outstanding_total_billed_and_total_received(): void
    {
        $down = $this->makeTerm($this->admin, $this->project, ['percentage' => 20]);
        $progress = $this->makeTerm($this->admin, $this->project, ['term_name' => 'Progress', 'percentage' => 30]);

        $first = $this->makeSentBilling($this->admin, $down, 200_000_000);
        $second = $this->makeSentBilling($this->admin, $progress, 150_000_000);
        $this->recordClientPayment($this->admin, $first, 200_000_000);
        $this->recordClientPayment($this->admin, $second, 50_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(350_000_000.0, $summary->totalBilled());
        $this->assertSame(250_000_000.0, $summary->totalReceived());
        $this->assertSame(100_000_000.0, $summary->outstanding());
        $this->assertSame(100_000_000.0, $summary->termOutstanding($progress));
    }

    public function test_cancelled_billing_is_excluded_and_releases_term(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);

        app(CancelClientBilling::class)->handle($this->admin, $billing, ['cancel_reason' => 'Nilai salah']);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(0.0, $summary->totalBilled());
        $this->assertSame(0, $summary->billingCount());
        $this->assertSame(0.0, $summary->outstanding());
        $this->assertSame(300_000_000.0, $summary->termRemaining($term));

        $this->makeDraftBilling($this->admin, $term, 300_000_000);

        $this->assertSame(2, ClientBilling::query()->count());
    }

    public function test_billing_does_not_change_boq_or_progress(): void
    {
        $boq = $this->makeApprovedBoq($this->admin, $this->project);
        $this->makePaidPlanForBoq($this->admin, $boq);
        $item = $boq->cogs->flatMap->items->first();
        app(UpdateItemProgress::class)->handle($this->admin, $item, 40);

        $boqTotal = $this->project->fresh()->totalCost();
        $itemsBefore = BoqItem::query()->orderBy('id')->get(['id', 'total_price', 'progress_percentage'])->toArray();
        $progressBefore = $this->project->fresh()->progressPercentage();

        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 50]);
        $billing = $this->makeSentBilling($this->admin, $term, 500_000_000);
        $this->recordClientPayment($this->admin, $billing, 500_000_000);

        $this->assertSame($boqTotal, $this->project->fresh()->totalCost());
        $this->assertSame($itemsBefore, BoqItem::query()->orderBy('id')->get(['id', 'total_price', 'progress_percentage'])->toArray());
        $this->assertSame($progressBefore, $this->project->fresh()->progressPercentage());

        $this->actingAs($this->admin)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Payment Keluar')
            ->assertDontSee('Terbayar');
    }

    public function test_billing_history_remains_after_cancellation(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $cancelled = $this->makeSentBilling($this->admin, $term, 100_000_000);
        app(CancelClientBilling::class)->handle($this->admin, $cancelled, ['cancel_reason' => 'Revisi nilai']);
        $this->makeSentBilling($this->admin, $term, 120_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(['BIL-0001', 'BIL-0002'], $summary->billings->pluck('billing_number')->all());
        $this->assertSame('Revisi nilai', $cancelled->fresh()->cancel_reason);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertOk()
            ->assertSeeInOrder(['BIL-0001', 'BIL-0002']);
    }

    public function test_project_summary_shows_billing_figures(): void
    {
        $down = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeTerm($this->admin, $this->project, ['term_name' => 'Pelunasan', 'percentage' => 70], ready: false);
        $billing = $this->makeSentBilling($this->admin, $down, 200_000_000);
        $this->recordClientPayment($this->admin, $billing, 50_000_000);
        $this->makeDraftBilling($this->admin, $down->fresh(), 50_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(1_000_000_000.0, $summary->contractValue());
        $this->assertSame(1_000_000_000.0, $summary->totalTermAmount());
        $this->assertSame(200_000_000.0, $summary->totalBilled());
        $this->assertSame(50_000_000.0, $summary->totalReceived());
        $this->assertSame(150_000_000.0, $summary->outstanding());
        $this->assertSame(1, $summary->billingCount());
        $this->assertSame(20.0, $summary->billedPercentage());
        $this->assertSame(5.0, $summary->receivedPercentage());

        $this->actingAs($this->admin)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Ringkasan Penagihan Client')
            ->assertSee('Rp150.000.000');
    }
}
