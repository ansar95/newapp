<?php

namespace Tests\Feature;

use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BillingTriggerAlertTest extends TestCase
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
        $this->makeApprovedBoq($this->admin, $this->project);
    }

    public function test_progress_and_date_triggers_require_their_condition(): void
    {
        $this->actingAs($this->admin)
            ->post(route('project-terms.store', $this->project), [
                'term_name' => 'Progress 50%',
                'calculation_type' => 'PERCENTAGE',
                'percentage' => '30',
                'trigger_type' => 'PROGRESS',
            ])
            ->assertSessionHasErrors('trigger_progress');

        $this->actingAs($this->admin)
            ->post(route('project-terms.store', $this->project), [
                'term_name' => 'Termin Tanggal',
                'calculation_type' => 'PERCENTAGE',
                'percentage' => '30',
                'trigger_type' => 'DATE',
            ])
            ->assertSessionHasErrors('due_date');

        $this->assertSame(0, $this->project->terms()->count());
    }

    public function test_alert_appears_once_project_progress_reaches_the_term_threshold_until_term_is_billed(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, [
            'term_name' => 'Progress 50%',
            'trigger_type' => 'PROGRESS',
            'trigger_progress' => 50,
        ], ready: false);
        $this->setProjectProgress(40);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertOk()
            ->assertSee('Progress fisik ≥ 50%')
            ->assertDontSee('Lakukan penagihan');

        $this->setProjectProgress(60);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertOk()
            ->assertSee('Lakukan penagihan: 1 termin sudah memenuhi syarat penagihan.')
            ->assertSee('Siap ditagih')
            ->assertSee('Tandai termin sebagai READY, lalu buat penagihan.');

        $this->actingAs($this->admin)->post(route('project-terms.readiness', $term), ['ready' => 1]);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertSee('Lakukan penagihan')
            ->assertDontSee('Tandai termin sebagai READY, lalu buat penagihan.')
            ->assertSee(route('client-billings.create', [$this->project, 'term' => $term->id]), false);

        $this->makeDraftBilling($this->admin, $term->fresh(), (float) $term->amount);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertDontSee('Lakukan penagihan')
            ->assertDontSee('Siap ditagih');
    }

    public function test_date_term_alerts_when_target_date_arrives_and_manual_term_never_alerts(): void
    {
        $this->makeTerm($this->admin, $this->project, ['term_name' => 'Uang Muka', 'percentage' => 20]);
        $this->makeTerm($this->admin, $this->project, [
            'term_name' => 'Termin Bulanan',
            'trigger_type' => 'DATE',
            'due_date' => now()->addDays(3)->toDateString(),
        ], ready: false);
        $this->setProjectProgress(100);

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertDontSee('Lakukan penagihan');

        $this->travel(3)->days();

        $this->actingAs($this->admin)
            ->get(route('projects.billing.index', $this->project))
            ->assertSee('Lakukan penagihan: 1 termin sudah memenuhi syarat penagihan.')
            ->assertSee('Termin Bulanan')
            ->assertSee('sudah tiba.');
    }

    private function setProjectProgress(float $percentage): void
    {
        BoqItem::query()->update(['progress_percentage' => $percentage]);
    }
}
