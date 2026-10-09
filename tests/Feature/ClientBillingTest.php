<?php

namespace Tests\Feature;

use App\Actions\Billing\BillingMessages;
use App\Actions\Billing\SendClientBilling;
use App\Actions\Billing\SubmitClientBilling;
use App\Enums\ClientBillingStatus;
use App\Enums\ProjectTermStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectBillingSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ClientBillingTest extends TestCase
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

    public function test_finance_can_create_billing_as_draft(): void
    {
        Storage::fake('public');
        $finance = $this->makeFinance();
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);

        $response = $this->actingAs($finance)->post(route('client-billings.store', $this->project), [
            'project_term_id' => $term->id,
            'billing_date' => now()->toDateString(),
            'billing_amount' => '100000000',
            'description' => 'Penagihan uang muka',
            'attachment' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
        ]);

        $billing = ClientBilling::query()->sole();

        $response->assertRedirect(route('client-billings.show', $billing))->assertSessionHasNoErrors();
        $this->assertSame('BIL-0001', $billing->billing_number);
        $this->assertSame(ClientBillingStatus::Draft, $billing->status);
        $this->assertSame($this->project->id, $billing->project_id);
        Storage::disk('public')->assertExists($billing->attachment);
    }

    public function test_billing_count_is_derived_from_sent_billings(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeSentBilling($this->admin, $term, 100_000_000);
        $this->makeSentBilling($this->admin, $term, 50_000_000);
        $this->makeDraftBilling($this->admin, $term, 10_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(2, $summary->billingCount());
        $this->assertSame(['BIL-0001', 'BIL-0002', 'BIL-0003'], $summary->billings->pluck('billing_number')->all());
    }

    public function test_billing_cannot_exceed_remaining_term_amount(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeDraftBilling($this->admin, $term, 200_000_000);

        $this->actingAs($this->admin)
            ->post(route('client-billings.store', $this->project), [
                'project_term_id' => $term->id,
                'billing_date' => now()->toDateString(),
                'billing_amount' => '100000000.01',
            ])
            ->assertSessionHasErrors(['business' => BillingMessages::BILLING_EXCEEDS_REMAINING]);

        $this->assertSame(1, ClientBilling::query()->count());
    }

    public function test_term_can_have_multiple_billings(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);

        $this->makeSentBilling($this->admin, $term, 100_000_000);
        $this->makeSentBilling($this->admin, $term, 120_000_000);
        $this->makeDraftBilling($this->admin, $term, 80_000_000);

        $this->assertSame(3, $term->billings()->count());
        $this->assertSame(0.0, ProjectBillingSummary::forProject($this->project)->termRemaining($term));
    }

    public function test_partial_billing_marks_term_partially_billed(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeSentBilling($this->admin, $term, 100_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(ProjectTermStatus::PartiallyBilled, $summary->termStatus($term));
        $this->assertSame(100_000_000.0, $summary->termBilled($term));
        $this->assertSame(200_000_000.0, $summary->termRemaining($term));
    }

    public function test_full_billing_marks_term_fully_billed(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeSentBilling($this->admin, $term, 100_000_000);
        $this->makeSentBilling($this->admin, $term, 200_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(ProjectTermStatus::FullyBilled, $summary->termStatus($term));
        $this->assertSame(0.0, $summary->termRemaining($term));
    }

    public function test_draft_billing_reserves_term_but_is_not_counted_as_billed(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeDraftBilling($this->admin, $term, 300_000_000);

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(0.0, $summary->totalBilled());
        $this->assertSame(0, $summary->billingCount());
        $this->assertSame(0.0, $summary->termRemaining($term));
        $this->assertSame(ProjectTermStatus::Ready, $summary->termStatus($term));
    }

    public function test_invoice_number_is_required_and_unique_on_send(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeSentBilling($this->admin, $term, 100_000_000, 'INV-001');
        $billing = $this->makeDraftBilling($this->admin, $term, 50_000_000);
        app(SubmitClientBilling::class)->handle($this->admin, $billing);

        try {
            app(SendClientBilling::class)->handle($this->admin, $billing->fresh(), ['invoice_date' => now()->toDateString()]);
            $this->fail('Invoice number should be required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_number', $exception->errors());
        }

        $this->expectException(ValidationException::class);

        app(SendClientBilling::class)->handle($this->admin, $billing->fresh(), [
            'invoice_number' => 'INV-001',
            'invoice_date' => now()->toDateString(),
        ]);
    }

    public function test_only_draft_billing_can_be_updated(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 100_000_000);

        $this->actingAs($this->admin)->get(route('client-billings.edit', $billing))->assertForbidden();
        $this->actingAs($this->admin)
            ->put(route('client-billings.update', $billing), [
                'billing_date' => now()->toDateString(),
                'billing_amount' => '1',
            ])
            ->assertForbidden();

        $this->assertSame(100_000_000.0, (float) $billing->fresh()->billing_amount);
    }

    public function test_draft_billing_update_excludes_itself_from_remaining(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeDraftBilling($this->admin, $term, 100_000_000);

        $this->actingAs($this->admin)
            ->put(route('client-billings.update', $billing), [
                'billing_date' => now()->toDateString(),
                'billing_amount' => '300000000',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(300_000_000.0, (float) $billing->fresh()->billing_amount);
    }

    public function test_billing_requires_active_or_completed_project(): void
    {
        $project = $this->makeActiveProject($this->admin, ['code' => 'PRJ-002', 'contract_value' => 1_000_000]);
        $term = $this->makeTerm($this->admin, $project);
        $project->update(['status' => 'DRAFT']);

        $this->expectException(BusinessRuleException::class);

        $this->makeDraftBilling($this->admin, $term->fresh(), 1000);
    }

    public function test_billing_pages_render(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 100_000_000, 'INV-RENDER-01');
        $this->recordClientPayment($this->admin, $billing, 40_000_000);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertOk()
            ->assertSee('Penagihan Client')
            ->assertSee('BIL-0001')
            ->assertSee('INV-RENDER-01')
            ->assertSee('Partially Billed')
            ->assertSee('RCV-0001');

        $this->actingAs($this->admin)
            ->get(route('client-billings.show', $billing))
            ->assertOk()
            ->assertSee('Partially Paid')
            ->assertSee('+ Record Payment');

        $this->actingAs($this->admin)->get(route('client-billings.create', $this->project))->assertOk();
    }
}
