<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectContractAlertTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-05 13:00:00');
    }

    public function test_project_ending_within_ten_days_is_flagged_as_ending_soon(): void
    {
        $project = $this->makeProjectEndingOn('2026-10-15');

        $this->assertSame(10, $project->contractDaysRemaining());
        $this->assertTrue($project->isContractEndingSoon());
        $this->assertFalse($project->isContractOverdue());
    }

    public function test_project_ending_in_eleven_days_is_not_flagged(): void
    {
        $project = $this->makeProjectEndingOn('2026-10-16');

        $this->assertSame(11, $project->contractDaysRemaining());
        $this->assertFalse($project->isContractEndingSoon());
        $this->assertFalse($project->isContractOverdue());
    }

    public function test_project_past_end_date_is_flagged_as_overdue(): void
    {
        $project = $this->makeProjectEndingOn('2026-10-02');

        $this->assertSame(-3, $project->contractDaysRemaining());
        $this->assertTrue($project->isContractOverdue());
        $this->assertFalse($project->isContractEndingSoon());
    }

    public function test_completed_project_is_never_flagged(): void
    {
        $project = $this->makeProjectEndingOn('2026-10-06', ProjectStatus::Completed);

        $this->assertNull($project->contractDaysRemaining());
        $this->assertFalse($project->isContractEndingSoon());
        $this->assertFalse($project->isContractOverdue());
    }

    public function test_project_detail_shows_contract_ending_alert(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeProjectEndingOn('2026-10-12', ProjectStatus::Active, $actor->id);

        $this->actingAs($actor)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Kontrak akan berakhir dalam 7 hari.');
    }

    public function test_project_detail_hides_alert_when_contract_end_is_far(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeProjectEndingOn('2026-12-31', ProjectStatus::Active, $actor->id);

        $this->actingAs($actor)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee('Kontrak akan berakhir')
            ->assertDontSee('Kontrak sudah berakhir');
    }

    public function test_dashboard_lists_only_projects_needing_contract_attention(): void
    {
        $actor = $this->makeSuperadmin();
        $this->makeProjectEndingOn('2026-10-10', ProjectStatus::Active, $actor->id, 'PRJ-SOON');
        $this->makeProjectEndingOn('2026-10-01', ProjectStatus::Active, $actor->id, 'PRJ-LATE');
        $this->makeProjectEndingOn('2026-11-30', ProjectStatus::Active, $actor->id, 'PRJ-FAR');
        $this->makeProjectEndingOn('2026-10-08', ProjectStatus::Completed, $actor->id, 'PRJ-DONE');

        $this->actingAs($actor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Peringatan Kontrak')
            ->assertSee('Sisa 5 hari')
            ->assertSee('Lewat 4 hari')
            ->assertViewHas('contractEndingProjects', fn ($projects) => $projects->pluck('code')->all() === ['PRJ-LATE', 'PRJ-SOON']);
    }

    private function makeProjectEndingOn(
        string $targetEndDate,
        ProjectStatus $status = ProjectStatus::Active,
        ?int $createdBy = null,
        ?string $code = null,
    ): Project {
        return Project::factory()->create(array_filter([
            'code' => $code,
            'status' => $status,
            'start_date' => '2026-01-01',
            'target_end_date' => $targetEndDate,
            'created_by' => $createdBy,
        ], fn ($value) => $value !== null));
    }
}
