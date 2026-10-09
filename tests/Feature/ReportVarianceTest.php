<?php

namespace Tests\Feature;

use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Payment\ProcessPayment;
use App\Enums\PaymentPlanType;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVarianceStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\ReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ReportVarianceTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
    }

    /**
     * Active project with an approved BOQ and a PAID payment worth the given share of the BOQ value.
     */
    private function projectWithActualShare(string $code, float $share): Project
    {
        $project = $this->makeActiveProject($this->admin, ['code' => $code, 'name' => 'Project '.$code]);
        $boq = $this->makeApprovedBoq($this->admin, $project, 'BOQ-'.$code);

        if ($share > 0) {
            $amount = round($project->fresh()->totalCost() * $share, 2);
            $item = $boq->cogs->first()->items->first();

            $plan = app(CreatePaymentPlan::class)->handle($this->admin, $boq, [
                'reference_code' => 'PP-'.$code,
                'type' => PaymentPlanType::ReadyToPay->value,
                'items' => [['boq_item_id' => $item->id, 'planned_amount' => $amount]],
            ]);

            app(ProcessPayment::class)->handle($this->admin, $plan, [
                'payment_code' => 'PAY-'.$code,
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'overrun_reason' => 'Uji laporan variance',
                'allocations' => [['boq_item_id' => $item->id, 'allocated_amount' => $amount]],
            ]);
        }

        return $project->fresh();
    }

    public function test_variance_status_boundaries_are_exact(): void
    {
        $this->assertSame(ProjectVarianceStatus::Normal, ProjectVarianceStatus::fromAmounts(1_000_000, 899_999.99));
        $this->assertSame(ProjectVarianceStatus::Warning, ProjectVarianceStatus::fromAmounts(1_000_000, 900_000));
        $this->assertSame(ProjectVarianceStatus::Warning, ProjectVarianceStatus::fromAmounts(1_000_000, 1_000_000));
        $this->assertSame(ProjectVarianceStatus::OverBudget, ProjectVarianceStatus::fromAmounts(1_000_000, 1_000_000.01));
        $this->assertSame(ProjectVarianceStatus::Normal, ProjectVarianceStatus::fromAmounts(1_000_000, 0));
    }

    public function test_projects_are_grouped_by_variance_and_sorted_worst_first(): void
    {
        $this->projectWithActualShare('PRJ-N', 0.5);
        $this->projectWithActualShare('PRJ-O', 1.2);
        $this->projectWithActualShare('PRJ-W', 0.95);
        $this->projectWithActualShare('PRJ-Z', 0);

        $report = app(ReportData::class)->variance();

        $this->assertSame(['PRJ-O', 'PRJ-W', 'PRJ-N', 'PRJ-Z'], $report['rows']->pluck('code')->all());
        $this->assertSame(
            [ProjectVarianceStatus::OverBudget, ProjectVarianceStatus::Warning, ProjectVarianceStatus::Normal, ProjectVarianceStatus::Normal],
            $report['rows']->pluck('variance_status')->all()
        );
        $this->assertSame(4, $report['summary']['total']);
        $this->assertSame(1, $report['summary']['over_budget']);
        $this->assertSame(1, $report['summary']['warning']);
        $this->assertSame(2, $report['summary']['normal']);

        $over = $report['rows']->first();
        $this->assertSame(120.0, $over['actual_percentage']);
        $this->assertLessThan(0, $over['variance']);
        $this->assertSame(round($over['boq_value'] - $over['actual_cost'], 2), $over['variance']);
    }

    public function test_only_active_and_completed_projects_with_approved_boq_are_included(): void
    {
        $this->projectWithActualShare('PRJ-A', 0.5);
        $this->makeActiveProject($this->admin, ['code' => 'PRJ-NOBOQ']);
        $completed = $this->projectWithActualShare('PRJ-C', 0.95);
        $completed->forceFill(['status' => ProjectStatus::Completed])->save();
        $onHold = $this->projectWithActualShare('PRJ-H', 1.1);
        $onHold->forceFill(['status' => ProjectStatus::Draft])->save();

        $codes = app(ReportData::class)->variance()['rows']->pluck('code')->all();

        $this->assertSame(['PRJ-C', 'PRJ-A'], $codes);
    }

    public function test_filter_shows_only_selected_category_but_keeps_summary_counts(): void
    {
        $this->projectWithActualShare('PRJ-N', 0.5);
        $this->projectWithActualShare('PRJ-O', 1.2);
        $this->projectWithActualShare('PRJ-W', 0.92);

        $this->actingAs($this->admin)
            ->get(route('reports.variance', ['variance' => ProjectVarianceStatus::Warning->value]))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->pluck('code')->all() === ['PRJ-W'])
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 3 && $summary['over_budget'] === 1)
            ->assertSee('Project PRJ-W')
            ->assertDontSee('Project PRJ-O')
            ->assertDontSee('Project PRJ-N');

        $this->actingAs($this->admin)
            ->get(route('reports.variance'))
            ->assertOk()
            ->assertSeeInOrder(['Project PRJ-O', 'Project PRJ-W', 'Project PRJ-N'])
            ->assertSee('Over Budget')
            ->assertSee('Mendekati')
            ->assertSee('Normal');
    }

    public function test_report_hub_links_to_variance_report(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee(route('reports.variance'), false);

        $this->actingAs($this->makeApprover())
            ->get(route('reports.variance'))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.variance'))->assertRedirect(route('login'));
    }
}
