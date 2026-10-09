<?php

namespace Tests\Feature;

use App\Enums\BmhpPurchaseStatus;
use App\Enums\ProjectStatus;
use App\Models\BmhpPurchase;
use App\Models\MasterBmhp;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BmhpAuthorizationTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private MasterBmhp $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin);
        $this->makeApprovedBoq($this->admin, $this->project);
        $this->master = $this->makeMasterBmhp($this->admin);
    }

    public function test_approver_can_only_view_bmhp(): void
    {
        $approver = $this->makeApprover();
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $this->project, [[$this->master, 1, 1000]]);

        $this->actingAs($approver)->get(route('projects.bmhp.index', $this->project))->assertOk();
        $this->actingAs($approver)->get(route('bmhp-purchases.show', $purchase))->assertOk();
        $this->actingAs($approver)->get(route('master-bmhps.index'))->assertOk();

        $this->actingAs($approver)->get(route('projects.bmhp.create', $this->project))->assertForbidden();
        $this->actingAs($approver)->post(route('projects.bmhp.store', $this->project), $this->headerPayload())->assertForbidden();
        $this->actingAs($approver)->post(route('bmhp-purchases.submit', $purchase))->assertForbidden();
        $this->actingAs($approver)->get(route('master-bmhps.create'))->assertForbidden();

        $this->assertSame(BmhpPurchaseStatus::Draft, $purchase->fresh()->status);
    }

    public function test_project_site_cannot_confirm_cancel_or_manage_master(): void
    {
        $site = $this->makeProjectSite();
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $this->project, [[$this->master, 1, 1000]]);

        $this->actingAs($site)->post(route('bmhp-purchases.submit', $purchase))->assertRedirect();
        $this->actingAs($site)->post(route('bmhp-purchases.confirm', $purchase))->assertForbidden();
        $this->actingAs($site)->post(route('bmhp-purchases.cancel', $purchase), ['cancel_reason' => 'Batal'])->assertForbidden();
        $this->actingAs($site)->post(route('master-bmhps.store'), [
            'name' => 'Paku',
            'category' => 'Material',
            'unit' => 'Kg',
        ])->assertForbidden();

        $this->assertSame(BmhpPurchaseStatus::Submitted, $purchase->fresh()->status);
    }

    public function test_finance_can_confirm_and_cancel_but_not_create_or_allocate(): void
    {
        $finance = $this->makeFinance();
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $this->project, [[$this->master, 1, 1000]]);
        $detail = $purchase->details->sole();

        $this->actingAs($finance)->post(route('projects.bmhp.store', $this->project), $this->headerPayload())->assertForbidden();
        $this->actingAs($finance)->put(route('bmhp-purchase-details.allocate', $detail), ['allocations' => []])->assertForbidden();

        $this->actingAs($this->admin)->post(route('bmhp-purchases.submit', $purchase));
        $this->actingAs($finance)->post(route('bmhp-purchases.confirm', $purchase))->assertRedirect();
        $this->actingAs($finance)->post(route('bmhp-purchases.cancel', $purchase), ['cancel_reason' => 'Salah input'])->assertRedirect();

        $this->assertSame(BmhpPurchaseStatus::Cancelled, $purchase->fresh()->status);
    }

    public function test_boq_cost_control_can_allocate_and_confirm_but_not_create_or_submit(): void
    {
        $costControl = $this->makeBoqCostControl();
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $this->project, [[$this->master, 1, 1000]]);
        $item = $this->project->boqs()->sole()->cogs->flatMap->items->first();

        $this->actingAs($costControl)->post(route('projects.bmhp.store', $this->project), $this->headerPayload())->assertForbidden();
        $this->actingAs($costControl)->post(route('bmhp-purchases.submit', $purchase))->assertForbidden();

        $this->actingAs($costControl)
            ->put(route('bmhp-purchase-details.allocate', $purchase->details->sole()), [
                'allocations' => [['selected' => '1', 'boq_item_id' => $item->id, 'allocated_amount' => '1000']],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('bmhp-purchases.submit', $purchase));
        $this->actingAs($costControl)->post(route('bmhp-purchases.confirm', $purchase))->assertRedirect();

        $this->assertSame(BmhpPurchaseStatus::Confirmed, $purchase->fresh()->status);
    }

    public function test_project_admin_can_manage_master_bmhp(): void
    {
        $projectAdmin = $this->makeProjectAdmin();

        $this->actingAs($projectAdmin)->get(route('master-bmhps.create'))->assertOk();
        $this->actingAs($projectAdmin)
            ->put(route('master-bmhps.update', $this->master), [
                'code' => $this->master->code,
                'name' => 'Gypsum 12mm',
                'category' => 'Material',
                'unit' => 'Lembar',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('master-bmhps.index'));

        $this->assertSame('Gypsum 12mm', $this->master->fresh()->name);
    }

    public function test_purchase_cannot_be_created_or_processed_on_non_active_project(): void
    {
        $site = $this->makeProjectSite();
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $this->project, [[$this->master, 1, 1000]]);
        $this->project->update(['status' => ProjectStatus::Completed]);

        $this->actingAs($site)->get(route('projects.bmhp.create', $this->project))->assertForbidden();
        $this->actingAs($site)->post(route('projects.bmhp.store', $this->project), $this->headerPayload())->assertForbidden();
        $this->actingAs($site)->post(route('bmhp-purchases.submit', $purchase))->assertForbidden();

        $this->assertSame(1, BmhpPurchase::query()->count());
    }

    public function test_bmhp_tab_is_visible_on_project_header(): void
    {
        $this->actingAs($this->makeApprover())
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee(route('projects.bmhp.index', $this->project), false);
    }

    /**
     * @return array<string, string>
     */
    private function headerPayload(): array
    {
        return [
            'purchase_date' => now()->toDateString(),
            'supplier' => 'PT ABC Material',
        ];
    }
}
