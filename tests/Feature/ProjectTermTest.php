<?php

namespace Tests\Feature;

use App\Actions\Billing\BillingMessages;
use App\Actions\Billing\CreateClientBilling;
use App\Actions\Billing\RemoveProjectTerm;
use App\Actions\Billing\SaveProjectTerm;
use App\Actions\Billing\SetProjectTermReadiness;
use App\Actions\Project\UpdateProject;
use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermReadiness;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectTermTest extends TestCase
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

    public function test_finance_can_create_percentage_and_fixed_terms(): void
    {
        $finance = $this->makeFinance();

        $this->actingAs($finance)
            ->post(route('project-terms.store', $this->project), [
                'term_name' => 'Uang Muka',
                'calculation_type' => 'PERCENTAGE',
                'percentage' => '20',
                'trigger_type' => 'MANUAL',
            ])
            ->assertRedirect(route('projects.billing.index', $this->project))
            ->assertSessionHasNoErrors();

        $this->actingAs($finance)
            ->post(route('project-terms.store', $this->project), [
                'term_name' => 'Progress 50%',
                'calculation_type' => 'FIXED_AMOUNT',
                'amount' => '300000000',
                'trigger_type' => 'PROGRESS',
                'trigger_value' => 'Progress fisik 50%',
                'trigger_progress' => '50',
            ])
            ->assertSessionHasNoErrors();

        $terms = $this->project->terms()->get();

        $this->assertCount(2, $terms);
        $this->assertSame([1, 2], $terms->pluck('term_number')->all());
        $this->assertSame(200_000_000.0, (float) $terms[0]->amount);
        $this->assertSame(ProjectTermCalculationType::FixedAmount, $terms[1]->calculation_type);
        $this->assertSame(300_000_000.0, (float) $terms[1]->amount);
        $this->assertSame(50.0, (float) $terms[1]->trigger_progress);
        $this->assertNull($terms[0]->trigger_progress);
        $this->assertSame(ProjectTermReadiness::NotReady, $terms[0]->readiness);
    }

    public function test_total_percentage_cannot_exceed_one_hundred(): void
    {
        $this->makeTerm($this->admin, $this->project, ['percentage' => 60]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage(BillingMessages::TERM_PERCENTAGE_EXCEEDS);

        $this->makeTerm($this->admin, $this->project, ['term_name' => 'Pelunasan', 'percentage' => 41]);
    }

    public function test_total_term_amount_cannot_exceed_contract_value(): void
    {
        $this->makeTerm($this->admin, $this->project, ['percentage' => 80]);

        $this->actingAs($this->admin)
            ->post(route('project-terms.store', $this->project), [
                'term_name' => 'Retensi',
                'calculation_type' => 'FIXED_AMOUNT',
                'amount' => '200000001',
            ])
            ->assertSessionHasErrors(['business' => BillingMessages::TERM_AMOUNT_EXCEEDS]);

        $this->assertSame(1, $this->project->terms()->count());
    }

    public function test_last_percentage_term_absorbs_rounding_when_total_is_one_hundred(): void
    {
        $this->project->update(['contract_value' => 1000]);

        foreach (['Termin A', 'Termin B', 'Termin C'] as $name) {
            $this->makeTerm($this->admin, $this->project->fresh(), [
                'term_name' => $name,
                'percentage' => $name === 'Termin C' ? 33.3334 : 33.3333,
            ], ready: false);
        }

        $amounts = $this->project->terms()->pluck('amount')->map(fn ($amount) => (float) $amount)->all();

        $this->assertSame([333.33, 333.33, 333.34], $amounts);
        $this->assertSame(1000.0, array_sum($amounts));
    }

    public function test_terms_require_contract_value(): void
    {
        $project = $this->makeActiveProject($this->admin, ['code' => 'PRJ-002']);

        $this->expectException(BusinessRuleException::class);

        $this->makeTerm($this->admin, $project);
    }

    public function test_contract_change_recalculates_percentage_terms(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 20]);
        $fixed = $this->makeTerm($this->admin, $this->project, [
            'term_name' => 'Retensi',
            'calculation_type' => 'FIXED_AMOUNT',
            'amount' => 100_000_000,
        ]);

        app(UpdateProject::class)->handle($this->admin, $this->project, $this->projectPayload(['contract_value' => 1_500_000_000]));

        $this->assertSame(300_000_000.0, (float) $term->fresh()->amount);
        $this->assertSame(100_000_000.0, (float) $fixed->fresh()->amount);
    }

    public function test_contract_change_is_rejected_when_term_falls_below_billed_amount(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 50]);
        $this->makeDraftBilling($this->admin, $term, 500_000_000);

        try {
            app(UpdateProject::class)->handle($this->admin, $this->project, $this->projectPayload(['contract_value' => 800_000_000]));
            $this->fail('Contract change should be rejected.');
        } catch (BusinessRuleException) {
        }

        $this->assertSame(1_000_000_000.0, (float) $this->project->fresh()->contract_value);
        $this->assertSame(500_000_000.0, (float) $term->fresh()->amount);
    }

    public function test_term_must_be_ready_before_billing(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ready: false);

        $this->expectException(BusinessRuleException::class);

        app(CreateClientBilling::class)->handle($this->admin, $this->project, [
            'project_term_id' => $term->id,
            'billing_date' => now()->toDateString(),
            'billing_amount' => 1000,
        ]);
    }

    public function test_term_with_active_billing_cannot_go_back_to_not_ready_or_be_removed(): void
    {
        $term = $this->makeTerm($this->admin, $this->project);
        $this->makeDraftBilling($this->admin, $term, 1000);

        try {
            app(SetProjectTermReadiness::class)->handle($this->admin, $term, false);
            $this->fail('Readiness change should be rejected.');
        } catch (BusinessRuleException) {
        }

        $this->expectException(BusinessRuleException::class);

        app(RemoveProjectTerm::class)->handle($this->admin, $term);
    }

    public function test_term_amount_cannot_be_reduced_below_reserved_billing(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->makeDraftBilling($this->admin, $term, 250_000_000);

        $this->expectException(BusinessRuleException::class);

        app(SaveProjectTerm::class)->handle($this->admin, $this->project, [
            'term_name' => 'Uang Muka',
            'calculation_type' => 'PERCENTAGE',
            'percentage' => 20,
        ], $term);
    }

    public function test_term_without_billing_is_deleted_and_term_with_cancelled_billing_is_cancelled(): void
    {
        $empty = $this->makeTerm($this->admin, $this->project, ['percentage' => 10]);
        $withHistory = $this->makeTerm($this->admin, $this->project, ['term_name' => 'Termin 2', 'percentage' => 10]);
        $billing = $this->makeDraftBilling($this->admin, $withHistory, 1000);

        $this->actingAs($this->admin)
            ->post(route('client-billings.cancel', $billing), ['cancel_reason' => 'Salah termin'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->delete(route('project-terms.destroy', $empty))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete(route('project-terms.destroy', $withHistory))->assertSessionHasNoErrors();

        $this->assertNull(ProjectTerm::query()->find($empty->id));
        $this->assertSame(ProjectTermReadiness::Cancelled, $withHistory->fresh()->readiness);
        $this->assertSame(1, $withHistory->billings()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function projectPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => $this->project->code,
            'name' => $this->project->name,
            'client_name' => $this->project->client_name,
            'contract_value' => $this->project->contract_value,
        ], $overrides);
    }
}
