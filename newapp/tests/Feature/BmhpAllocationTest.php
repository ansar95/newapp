<?php

namespace Tests\Feature;

use App\Actions\Bmhp\AllocateBmhpPurchaseDetail;
use App\Actions\Bmhp\BmhpAllocationMath;
use App\Actions\Bmhp\SaveBmhpPurchaseDetail;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchaseDetail;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BmhpAllocationTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Boq $boq;

    /**
     * @var Collection<string, BoqItem>
     */
    private Collection $items;

    private BmhpPurchaseDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($this->admin);
        $this->boq = $this->makeApprovedBoq($this->admin, $project);
        $this->items = $this->boq->cogs->flatMap->items->keyBy('code');

        $purchase = $this->makeDraftBmhpPurchase($this->admin, $project, [
            [$this->makeMasterBmhp($this->admin), 100, 250000],
        ]);
        $this->detail = $purchase->details->sole();
    }

    public function test_detail_can_be_allocated_to_a_boq_item(): void
    {
        $this->actingAs($this->admin)
            ->put(route('bmhp-purchase-details.allocate', $this->detail), [
                'allocations' => [
                    ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => '15000000', 'allocated_quantity' => '60'],
                    ['boq_item_id' => $this->items['ITEM-E02']->id, 'allocated_amount' => '5000000'],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $allocation = $this->detail->allocations()->sole();

        $this->assertSame($this->items['ITEM-E01']->id, $allocation->boq_item_id);
        $this->assertSame('15000000.00', $allocation->allocated_amount);
        $this->assertSame('60.0000', $allocation->allocated_quantity);
    }

    public function test_detail_can_be_allocated_to_multiple_boq_items(): void
    {
        app(AllocateBmhpPurchaseDetail::class)->handle($this->admin, $this->detail, [
            ['boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => 15000000],
            ['boq_item_id' => $this->items['ITEM-E02']->id, 'allocated_amount' => 10000000],
        ]);

        $this->assertSame(2, $this->detail->allocations()->count());
        $this->assertSame(25000000.0, $this->detail->fresh()->allocatedAmount());
        $this->assertSame(0.0, $this->detail->fresh()->unallocatedAmount());
    }

    public function test_partial_allocation_leaves_unallocated_amount(): void
    {
        app(AllocateBmhpPurchaseDetail::class)->handle($this->admin, $this->detail, [
            ['boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => 20000000],
        ]);

        $this->assertSame(5000000.0, $this->detail->fresh()->unallocatedAmount());
    }

    public function test_allocation_replaces_previous_allocations_of_the_detail(): void
    {
        $action = app(AllocateBmhpPurchaseDetail::class);
        $action->handle($this->admin, $this->detail, [
            ['boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => 20000000],
        ]);
        $action->handle($this->admin, $this->detail->fresh(), [
            ['boq_item_id' => $this->items['ITEM-E02']->id, 'allocated_amount' => 25000000],
        ]);

        $allocation = $this->detail->allocations()->sole();
        $this->assertSame($this->items['ITEM-E02']->id, $allocation->boq_item_id);
    }

    public function test_allocation_cannot_exceed_detail_total(): void
    {
        $this->actingAs($this->admin)
            ->from(route('bmhp-purchases.show', $this->detail->bmhp_purchase_id))
            ->put(route('bmhp-purchase-details.allocate', $this->detail), [
                'allocations' => [
                    ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => '20000000'],
                    ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E02']->id, 'allocated_amount' => '5000000.01'],
                ],
            ])
            ->assertRedirect(route('bmhp-purchases.show', $this->detail->bmhp_purchase_id))
            ->assertSessionHasErrors(['business' => BmhpAllocationMath::EXCEEDS_MESSAGE]);

        $this->assertSame(0, $this->detail->allocations()->count());
    }

    public function test_allocation_quantity_cannot_exceed_detail_quantity(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(AllocateBmhpPurchaseDetail::class)->handle($this->admin, $this->detail, [
            ['boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => 1000, 'allocated_quantity' => 60],
            ['boq_item_id' => $this->items['ITEM-E02']->id, 'allocated_amount' => 1000, 'allocated_quantity' => 41],
        ]);
    }

    public function test_allocation_only_targets_boq_items_of_the_same_project(): void
    {
        $otherProject = $this->makeActiveProject($this->admin, ['code' => 'PRJ-002', 'name' => 'Other Project']);
        $otherBoq = $this->makeApprovedBoq($this->admin, $otherProject, 'BOQ-PRJ002');
        $foreignItem = $otherBoq->cogs->flatMap->items->first();

        try {
            app(AllocateBmhpPurchaseDetail::class)->handle($this->admin, $this->detail, [
                ['boq_item_id' => $foreignItem->id, 'allocated_amount' => 1000],
            ]);
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('project yang sama', $exception->getMessage());
        }

        $this->assertSame(0, $this->detail->allocations()->count());
    }

    public function test_allocation_only_targets_items_of_approved_boq(): void
    {
        $otherProject = $this->makeActiveProject($this->admin, ['code' => 'PRJ-003', 'name' => 'Draft Project']);
        $draftBoq = $this->makeDraftBoq($this->admin, $otherProject, code: 'BOQ-PRJ003');
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $otherProject, [[$this->makeMasterBmhp($this->admin, ['name' => 'Kabel']), 1, 1000]]);

        $this->expectException(BusinessRuleException::class);

        app(AllocateBmhpPurchaseDetail::class)->handle($this->admin, $purchase->details->sole(), [
            ['boq_item_id' => $draftBoq->cogs->flatMap->items->first()->id, 'allocated_amount' => 500],
        ]);
    }

    public function test_detail_total_cannot_be_reduced_below_allocated_amount(): void
    {
        app(AllocateBmhpPurchaseDetail::class)->handle($this->admin, $this->detail, [
            ['boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => 20000000],
        ]);

        try {
            app(SaveBmhpPurchaseDetail::class)->handle($this->admin, $this->detail->purchase, [
                'master_bmhp_id' => $this->detail->master_bmhp_id,
                'quantity' => 50,
                'unit_price' => 250000,
            ], $this->detail->fresh());
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame(BmhpAllocationMath::EXCEEDS_MESSAGE, $exception->getMessage());
        }

        $this->assertSame('100.0000', $this->detail->fresh()->quantity);
    }

    public function test_allocation_is_locked_after_submit(): void
    {
        $this->submitAndConfirmBmhpPurchase($this->admin, $this->detail->purchase);

        $this->actingAs($this->admin)
            ->put(route('bmhp-purchase-details.allocate', $this->detail), [
                'allocations' => [
                    ['selected' => '1', 'boq_item_id' => $this->items['ITEM-E01']->id, 'allocated_amount' => '1000'],
                ],
            ])
            ->assertForbidden();

        $this->assertSame(0, $this->detail->allocations()->count());
    }
}
