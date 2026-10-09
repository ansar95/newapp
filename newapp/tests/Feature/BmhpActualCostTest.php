<?php

namespace Tests\Feature;

use App\Actions\Bmhp\AllocateBmhpPurchaseDetail;
use App\Actions\Bmhp\CancelBmhpPurchase;
use App\Actions\Bmhp\SubmitBmhpPurchase;
use App\Actions\Progress\UpdateItemProgress;
use App\Models\BmhpPurchase;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;
use App\Support\BmhpActualCost;
use App\Support\ProjectDetailContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BmhpActualCostTest extends TestCase
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

    public function test_confirmed_purchase_counts_as_actual(): void
    {
        $this->makeAllocatedPurchase([[3000000, ['ITEM-E01' => 1500000, 'ITEM-E02' => 1000000]]], confirm: true);

        $actual = BmhpActualCost::forProject($this->project);

        $this->assertSame(2500000.0, $actual->projectActual());
        $this->assertSame(1, $actual->confirmedPurchaseCount);
    }

    public function test_draft_and_submitted_purchases_do_not_count_as_actual(): void
    {
        $this->makeAllocatedPurchase([[1000000, ['ITEM-E01' => 1000000]]]);
        $submitted = $this->makeAllocatedPurchase([[2000000, ['ITEM-E02' => 2000000]]]);
        app(SubmitBmhpPurchase::class)->handle($this->admin, $submitted);

        $actual = BmhpActualCost::forProject($this->project);

        $this->assertSame(0.0, $actual->projectActual());
        $this->assertSame(0.0, $actual->forItem($this->items['ITEM-E01']));
        $this->assertSame(0, $actual->confirmedPurchaseCount);
    }

    public function test_cancelled_purchase_is_removed_from_actual(): void
    {
        $purchase = $this->makeAllocatedPurchase([[2000000, ['ITEM-E03' => 2000000]]], confirm: true);
        $this->assertSame(2000000.0, BmhpActualCost::forProject($this->project)->projectActual());

        app(CancelBmhpPurchase::class)->handle($this->admin, $purchase->fresh(), ['cancel_reason' => 'Duplikat transaksi']);

        $actual = BmhpActualCost::forProject($this->project);
        $this->assertSame(0.0, $actual->projectActual());
        $this->assertSame(0.0, $actual->forItem($this->items['ITEM-E03']));
        $this->assertSame(1, $purchase->details()->sole()->allocations()->count());
    }

    public function test_actual_per_item_cogs_and_project_with_variance_and_percentage(): void
    {
        $this->makeAllocatedPurchase([
            [3000000, ['ITEM-E01' => 1500000, 'ITEM-E02' => 1000000]],
            [500000, ['ITEM-I01' => 500000]],
        ], confirm: true);
        $this->makeAllocatedPurchase([[1000000, ['ITEM-E01' => 1000000]]]);
        $cancelled = $this->makeAllocatedPurchase([[2000000, ['ITEM-E03' => 2000000]]], confirm: true);
        app(CancelBmhpPurchase::class)->handle($this->admin, $cancelled->fresh(), ['cancel_reason' => 'Salah project']);

        $actual = BmhpActualCost::forProject($this->project);
        [$engineering, $installation] = $this->boq->cogs->sortBy('sort_order')->values()->all();

        $this->assertSame(1500000.0, $actual->forItem($this->items['ITEM-E01']));
        $this->assertSame(1000000.0, $actual->forItem($this->items['ITEM-E02']));
        $this->assertSame(0.0, $actual->forItem($this->items['ITEM-E03']));
        $this->assertSame(500000.0, $actual->forItem($this->items['ITEM-I01']));

        $this->assertSame(2500000.0, $actual->forCogs($engineering));
        $this->assertSame(500000.0, $actual->forCogs($installation));

        $this->assertSame(3000000.0, $actual->forBoq($this->boq));
        $this->assertSame(3000000.0, $actual->projectActual());
        $this->assertSame(3500000.0, $actual->confirmedPurchaseTotal);
        $this->assertSame(500000.0, $actual->unallocatedAmount());

        $this->assertSame(500000.0, BmhpActualCost::variance((float) $this->items['ITEM-E01']->total_price, $actual->forItem($this->items['ITEM-E01'])));
        $this->assertSame(75.0, BmhpActualCost::percentage((float) $this->items['ITEM-E01']->total_price, $actual->forItem($this->items['ITEM-E01'])));

        $context = ProjectDetailContext::for($this->project->fresh(), $this->admin);
        $this->assertSame(11000000.0, (float) $context->totalBoqValue);
        $this->assertSame(3000000.0, $context->bmhpActual);
        $this->assertSame(3000000.0, $context->totalActual);
        $this->assertSame(8000000.0, $context->actualVariance);
        $this->assertSame(27.27, $context->actualPercentage);
    }

    public function test_actual_above_baseline_produces_negative_variance(): void
    {
        $this->assertSame(-500000.0, BmhpActualCost::variance(2000000, 2500000));
        $this->assertSame(125.0, BmhpActualCost::percentage(2000000, 2500000));
        $this->assertSame(0.0, BmhpActualCost::percentage(0, 2500000));
    }

    public function test_bmhp_does_not_change_boq_baseline(): void
    {
        $before = $this->baselineSnapshot();

        $this->makeAllocatedPurchase([[4000000, ['ITEM-E01' => 3000000, 'ITEM-E02' => 1000000]]], confirm: true);

        $this->assertSame($before, $this->baselineSnapshot());
        $this->assertSame(11000000.0, $this->project->fresh()->totalCost());
    }

    public function test_bmhp_does_not_change_progress(): void
    {
        $this->makePaidPlanForBoq($this->admin, $this->boq, amount: 2000000);
        app(UpdateItemProgress::class)->handle($this->admin, $this->items['ITEM-E01']->fresh(), 40);
        $projectProgressBefore = $this->project->fresh()->progressPercentage();

        $this->makeAllocatedPurchase([[2000000, ['ITEM-E01' => 2000000]]], confirm: true);

        $this->assertSame('40.00', $this->items['ITEM-E01']->fresh()->progress_percentage);
        $this->assertSame($projectProgressBefore, $this->project->fresh()->progressPercentage());
    }

    public function test_bmhp_does_not_change_payment(): void
    {
        $plan = $this->makePaidPlanForBoq($this->admin, $this->boq, amount: 2000000);
        $paymentsBefore = $plan->payments()->get(['id', 'amount', 'status'])->toArray();
        $planBefore = $plan->only(['amount', 'status']);

        $this->makeAllocatedPurchase([[2000000, ['ITEM-E01' => 2000000]]], confirm: true);

        $plan->refresh();
        $this->assertSame($planBefore, $plan->only(['amount', 'status']));
        $this->assertSame($paymentsBefore, $plan->payments()->get(['id', 'amount', 'status'])->toArray());
    }

    public function test_overview_and_boq_page_show_cost_control_figures(): void
    {
        $this->makeAllocatedPurchase([[3000000, ['ITEM-E01' => 1500000]]], confirm: true);

        $this->actingAs($this->admin)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Project Cost Control')
            ->assertSee('Rp1.500.000');

        $this->actingAs($this->admin)
            ->get(route('boqs.show', $this->boq))
            ->assertOk()
            ->assertSee('Total Actual')
            ->assertSee('Rp500.000');
    }

    /**
     * @param  list<array{0: int, 1: array<string, int>}>  $lines  [detail total, [item code => allocated amount]]
     */
    private function makeAllocatedPurchase(array $lines, bool $confirm = false): BmhpPurchase
    {
        $purchase = $this->makeDraftBmhpPurchase(
            $this->admin,
            $this->project,
            array_map(fn (array $line) => [$this->makeMasterBmhp($this->admin), 1, $line[0]], $lines),
        );

        foreach ($purchase->details->values() as $index => $detail) {
            app(AllocateBmhpPurchaseDetail::class)->handle(
                $this->admin,
                $detail,
                collect($lines[$index][1])
                    ->map(fn (int $amount, string $code) => ['boq_item_id' => $this->items[$code]->id, 'allocated_amount' => $amount])
                    ->values()
                    ->all(),
            );
        }

        return $confirm ? $this->submitAndConfirmBmhpPurchase($this->admin, $purchase) : $purchase->fresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function baselineSnapshot(): array
    {
        return BoqItem::query()
            ->orderBy('id')
            ->get(['id', 'quantity', 'unit_price', 'total_price', 'progress_percentage'])
            ->push($this->boq->fresh()->only(['status', 'revision_number']))
            ->toArray();
    }
}
