<?php

namespace Tests\Feature;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProcurementAuthorizationTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin);
    }

    public function test_project_site_and_project_admin_can_create_and_submit_but_not_approve_or_pay(): void
    {
        foreach ([$this->makeProjectSite(), $this->makeProjectAdmin()] as $requester) {
            $this->actingAs($requester)->get(route('projects.procurement.create', $this->project))->assertOk();

            $request = $this->makeDraftProcurementRequest($requester, $this->project);

            $this->actingAs($requester)->get(route('procurement-requests.edit', $request))->assertOk();
            $this->actingAs($requester)->post(route('procurement-requests.submit', $request))->assertRedirect();
            $this->actingAs($requester)->post(route('procurement-requests.approve', $request))->assertForbidden();

            $this->actingAs($this->makeApprover())->post(route('procurement-requests.approve', $request));

            $this->actingAs($requester)->get(route('procurement-requests.pay', $request))->assertForbidden();
            $this->actingAs($requester)
                ->post(route('procurement-requests.cancel', $request), ['cancel_reason' => 'Batal'])
                ->assertForbidden();
        }
    }

    public function test_approver_only_approves_and_finance_only_pays_or_cancels(): void
    {
        $approver = $this->makeApprover();
        $finance = $this->makeFinance();
        $request = $this->makeDraftProcurementRequest($this->makeProjectSite(), $this->project);

        foreach ([$approver, $finance] as $user) {
            $this->actingAs($user)->get(route('projects.procurement.index', $this->project))->assertOk();
            $this->actingAs($user)->get(route('procurement-requests.show', $request))->assertOk();
            $this->actingAs($user)->get(route('projects.procurement.create', $this->project))->assertForbidden();
            $this->actingAs($user)->post(route('procurement-requests.submit', $request))->assertForbidden();
        }

        $request->update(['status' => ProcurementStatus::Submitted]);

        $this->actingAs($finance)->post(route('procurement-requests.approve', $request))->assertForbidden();
        $this->actingAs($approver)->post(route('procurement-requests.approve', $request))->assertRedirect();

        $this->actingAs($approver)->get(route('procurement-requests.pay', $request))->assertForbidden();
        $this->actingAs($approver)
            ->post(route('procurement-requests.cancel', $request), ['cancel_reason' => 'Batal'])
            ->assertForbidden();
        $this->actingAs($finance)->get(route('procurement-requests.pay', $request))->assertOk();
    }

    public function test_boq_cost_control_can_view_and_allocate_but_not_create(): void
    {
        $costControl = $this->makeBoqCostControl();
        $boq = $this->makeApprovedBoq($this->admin, $this->project);
        $paid = $this->makePaidProcurementRequest($this->admin, $this->project);

        $this->actingAs($costControl)->get(route('projects.procurement.index', $this->project))->assertOk();
        $this->actingAs($costControl)->get(route('projects.procurement.create', $this->project))->assertForbidden();
        $this->actingAs($costControl)
            ->get(route('projects.bmhp.index', $this->project))
            ->assertOk()
            ->assertSee('Simpan Alokasi');

        $this->actingAs($costControl)
            ->put(route('procurement-request-items.allocate', $paid->items->sole()), [
                'allocations' => [
                    $boq->cogs->first()->items->first()->id => [
                        'selected' => '1',
                        'boq_item_id' => $boq->cogs->first()->items->first()->id,
                        'allocated_amount' => 100000,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(100000.0, $paid->items->sole()->fresh()->allocatedAmount());
    }

    public function test_approver_and_finance_cannot_allocate(): void
    {
        $paid = $this->makePaidProcurementRequest($this->admin, $this->project);

        foreach ([$this->makeApprover(), $this->makeFinance()] as $user) {
            $this->actingAs($user)
                ->get(route('projects.bmhp.index', $this->project))
                ->assertOk()
                ->assertDontSee('Simpan Alokasi');

            $this->actingAs($user)
                ->put(route('procurement-request-items.allocate', $paid->items->sole()), ['allocations' => []])
                ->assertForbidden();
        }
    }

    public function test_requests_cannot_be_created_or_processed_when_project_is_not_active(): void
    {
        $site = $this->makeProjectSite();
        $request = $this->makeDraftProcurementRequest($site, $this->project);

        $this->project->update(['status' => ProjectStatus::Completed]);

        $this->actingAs($site)->get(route('projects.procurement.index', $this->project))->assertOk();
        $this->actingAs($site)->get(route('projects.procurement.create', $this->project))->assertForbidden();
        $this->actingAs($site)->post(route('procurement-requests.submit', $request))->assertForbidden();
    }

    public function test_tabs_are_shown_to_roles_with_procurement_access(): void
    {
        $this->actingAs($this->makeProjectSite())
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee(route('projects.procurement.index', $this->project), false)
            ->assertSee(route('projects.bmhp.index', $this->project), false)
            ->assertSee(route('projects.rentals.index', $this->project), false);

        $this->actingAs($this->makeProjectSite())
            ->get(route('projects.rentals.index', $this->project))
            ->assertOk()
            ->assertSee('Belum ada permintaan '.ProcurementType::Rental->label().' yang dibayar.');
    }
}
