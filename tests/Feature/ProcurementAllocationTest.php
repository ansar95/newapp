<?php

namespace Tests\Feature;

use App\Actions\Procurement\AllocateProcurementItem;
use App\Enums\ProcurementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectActualCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProcurementAllocationTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private User $site;

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
        $this->site = $this->makeProjectSite();
        $this->project = $this->makeActiveProject($this->admin);
        $this->boq = $this->makeApprovedBoq($this->admin, $this->project);
        $this->items = $this->boq->cogs->flatMap->items->keyBy('code');
    }

    public function test_bmhp_and_rental_allocations_become_separate_actual_costs(): void
    {
        $bmhp = $this->makePaidProcurementRequest($this->site, $this->project, ProcurementType::Bmhp, [
            $this->procurementItemPayload(ProcurementType::Bmhp, 10, 50000),
        ]);
        $rental = $this->makePaidProcurementRequest($this->site, $this->project, ProcurementType::Rental, [
            $this->procurementItemPayload(ProcurementType::Rental, 2, 150000, 'Scaffolding'),
        ]);

        $this->allocateViaHttp($bmhp, ['ITEM-E01' => 300000, 'ITEM-I01' => 200000])->assertSessionHasNoErrors();
        $this->allocateViaHttp($rental, ['ITEM-E01' => 300000])->assertSessionHasNoErrors();

        $actualCost = ProjectActualCost::forProject($this->project->fresh());

        $this->assertSame(500000.0, $actualCost->bmhpActual());
        $this->assertSame(300000.0, $actualCost->rentalActual());
        $this->assertSame(600000.0, $actualCost->forItem($this->items['ITEM-E01']));
        $this->assertSame(800000.0, $actualCost->projectActual());

        $this->actingAs($this->site)
            ->get(route('projects.bmhp.index', $this->project))
            ->assertOk()
            ->assertSee($bmhp->request_number)
            ->assertDontSee($rental->request_number);

        $this->actingAs($this->site)
            ->get(route('projects.rentals.index', $this->project))
            ->assertOk()
            ->assertSee($rental->request_number)
            ->assertDontSee($bmhp->request_number)
            ->assertSee('Scaffolding');
    }

    public function test_allocation_can_be_changed_after_payment(): void
    {
        $request = $this->makePaidProcurementRequest($this->site, $this->project);

        $this->allocateViaHttp($request, ['ITEM-E01' => 100000])->assertSessionHasNoErrors();
        $this->allocateViaHttp($request, ['ITEM-E02' => 40000, 'ITEM-E03' => 60000])->assertSessionHasNoErrors();

        $allocations = $request->items->sole()->allocations()->get();

        $this->assertSame(
            [$this->items['ITEM-E02']->id, $this->items['ITEM-E03']->id],
            $allocations->pluck('boq_item_id')->all()
        );
        $this->assertSame(0.0, ProjectActualCost::forProject($this->project->fresh())->forItem($this->items['ITEM-E01']));
    }

    public function test_allocation_cannot_exceed_paid_value(): void
    {
        $request = $this->makePaidProcurementRequest($this->site, $this->project);

        $this->allocateViaHttp($request, ['ITEM-E01' => 60000, 'ITEM-E02' => 50000])
            ->assertSessionHasErrors('business');

        $this->assertSame(0, $request->items->sole()->allocations()->count());
    }

    public function test_only_paid_bmhp_or_rental_items_can_be_allocated(): void
    {
        $draft = $this->makeDraftProcurementRequest($this->site, $this->project);

        $this->allocateViaHttp($draft, ['ITEM-E01' => 1000])->assertForbidden();

        $asset = $this->makePaidProcurementRequest($this->site, $this->project, ProcurementType::Asset, [
            $this->procurementItemPayload(ProcurementType::Asset, 1, 1000000, 'Bor Listrik'),
        ]);

        $this->allocateViaHttp($asset, ['ITEM-E01' => 1000])->assertForbidden();

        $this->expectException(BusinessRuleException::class);

        app(AllocateProcurementItem::class)->handle($this->admin, $asset->items->sole(), [
            ['boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => 1000],
        ]);
    }

    public function test_allocation_target_must_be_an_approved_boq_item_of_the_same_project(): void
    {
        $otherProject = $this->makeActiveProject($this->admin, ['code' => 'PRJ-002']);
        $otherBoq = $this->makeApprovedBoq($this->admin, $otherProject, 'BOQ-PRJ002');
        $request = $this->makePaidProcurementRequest($this->site, $this->project);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('BOQ APPROVED pada project yang sama');

        app(AllocateProcurementItem::class)->handle($this->site, $request->items->sole(), [
            ['boq_item_id' => $otherBoq->cogs->first()->items->first()->id, 'allocated_amount' => 1000],
        ]);
    }

    /**
     * @param  array<string, float|int>  $amountsByCode
     */
    private function allocateViaHttp(ProcurementRequest $request, array $amountsByCode): TestResponse
    {
        $allocations = collect($amountsByCode)->mapWithKeys(fn ($amount, string $code) => [
            $this->items[$code]->id => [
                'selected' => '1',
                'boq_item_id' => $this->items[$code]->id,
                'allocated_amount' => $amount,
            ],
        ])->all();

        return $this->actingAs($this->site)
            ->from(route('projects.bmhp.index', $this->project))
            ->put(route('procurement-request-items.allocate', $request->items->first()), ['allocations' => $allocations]);
    }
}
