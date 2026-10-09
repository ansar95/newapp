<?php

namespace Tests\Feature;

use App\Enums\ClientBillingStatus;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BillingAuthorizationTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private ProjectTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin, ['contract_value' => 1_000_000_000]);
        $this->term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
    }

    public function test_project_site_has_no_billing_access(): void
    {
        $site = $this->makeProjectSite();
        $billing = $this->makeSentBilling($this->admin, $this->term, 1000);

        $this->actingAs($site)->get(route('projects.billing.index', $this->project))->assertForbidden();
        $this->actingAs($site)->get(route('client-billings.show', $billing))->assertForbidden();
        $this->actingAs($site)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertDontSee(route('projects.billing.index', $this->project), false);
    }

    public function test_approver_and_cost_control_can_only_view(): void
    {
        $billing = $this->makeSentBilling($this->admin, $this->term, 1000);

        foreach ([$this->makeApprover(), $this->makeBoqCostControl()] as $user) {
            $this->actingAs($user)->get(route('projects.billing.index', $this->project))->assertOk();
            $this->actingAs($user)->get(route('client-billings.show', $billing))->assertOk();
            $this->actingAs($user)->get(route('client-billings.create', $this->project))->assertForbidden();
            $this->actingAs($user)->post(route('project-terms.store', $this->project), ['term_name' => 'X'])->assertForbidden();
            $this->actingAs($user)->post(route('client-payments.store', $billing), ['amount' => 1])->assertForbidden();
            $this->actingAs($user)->post(route('client-billings.cancel', $billing), ['cancel_reason' => 'Batal'])->assertForbidden();
        }

        $this->actingAs($this->makeApprover())->get(route('client-billings.show', $billing))->assertSee('Pembayaran Client');
        $this->actingAs($this->makeBoqCostControl())->get(route('client-billings.show', $billing))->assertDontSee('Pembayaran Client');
    }

    public function test_project_admin_can_prepare_but_not_send_cancel_or_record_payment(): void
    {
        $projectAdmin = $this->makeProjectAdmin();

        $this->actingAs($projectAdmin)
            ->post(route('project-terms.store', $this->project), [
                'term_name' => 'Retensi',
                'calculation_type' => 'PERCENTAGE',
                'percentage' => '10',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($projectAdmin)
            ->post(route('client-billings.store', $this->project), [
                'project_term_id' => $this->term->id,
                'billing_date' => now()->toDateString(),
                'billing_amount' => '1000',
            ])
            ->assertSessionHasNoErrors();

        $billing = $this->project->clientBillings()->sole();

        $this->actingAs($projectAdmin)->post(route('client-billings.submit', $billing))->assertSessionHasNoErrors();
        $this->actingAs($projectAdmin)
            ->post(route('client-billings.send', $billing), ['invoice_number' => 'INV-1', 'invoice_date' => now()->toDateString()])
            ->assertForbidden();
        $this->actingAs($projectAdmin)->post(route('client-billings.cancel', $billing), ['cancel_reason' => 'Batal'])->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('client-billings.send', $billing), ['invoice_number' => 'INV-1', 'invoice_date' => now()->toDateString()])
            ->assertSessionHasNoErrors();

        $this->actingAs($projectAdmin)
            ->post(route('client-payments.store', $billing), ['payment_date' => now()->toDateString(), 'amount' => '1000'])
            ->assertForbidden();

        $this->assertSame(ClientBillingStatus::Sent, $billing->fresh()->status);
    }

    public function test_finance_can_send_record_payment_and_cancel(): void
    {
        $finance = $this->makeFinance();
        $billing = $this->makeDraftBilling($this->admin, $this->term, 1000);

        $this->actingAs($finance)->post(route('client-billings.submit', $billing))->assertSessionHasNoErrors();
        $this->actingAs($finance)
            ->post(route('client-billings.send', $billing), ['invoice_number' => 'INV-9', 'invoice_date' => now()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->actingAs($finance)
            ->post(route('client-payments.store', $billing), ['payment_date' => now()->toDateString(), 'amount' => '400'])
            ->assertSessionHasNoErrors();

        $payment = $billing->payments()->sole();

        $this->actingAs($finance)->post(route('client-payments.cancel', $payment), ['cancel_reason' => 'Salah input'])->assertSessionHasNoErrors();
        $this->actingAs($finance)->post(route('client-billings.cancel', $billing), ['cancel_reason' => 'Revisi'])->assertSessionHasNoErrors();

        $this->assertSame(ClientBillingStatus::Cancelled, $billing->fresh()->status);
    }

    public function test_billing_tab_is_visible_between_bmhp_and_payment(): void
    {
        $this->actingAs($this->makeFinance())
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSeeInOrder([
                route('projects.boq.index', $this->project),
                route('projects.assets.index', $this->project),
                route('projects.bmhp.index', $this->project),
                route('projects.billing.index', $this->project),
                route('projects.payment.index', $this->project),
                route('projects.progress.index', $this->project),
            ], false);
    }
}
