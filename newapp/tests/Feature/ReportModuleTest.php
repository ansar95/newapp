<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ReportModuleTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_superadmin_can_open_reports_hub(): void
    {
        $actor = $this->makeSuperadmin();

        $this->actingAs($actor)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Laporan')
            ->assertSee('Projects')
            ->assertSee('Finance')
            ->assertSee('Progress')
            ->assertSee('Assets');
    }

    public function test_project_admin_can_open_project_report_and_export(): void
    {
        $actor = $this->makeProjectAdmin();
        $this->makeActiveProject($actor, ['code' => 'PRJ-RPT-01']);

        $this->assertTrue($actor->hasPermission(Permission::ReportView));

        $this->actingAs($actor)
            ->get(route('reports.projects'))
            ->assertOk()
            ->assertSee('PRJ-RPT-01');

        $export = $this->actingAs($actor)->get(route('reports.projects.export'));
        $export->assertOk();
        $content = $export->streamedContent();
        $this->assertStringContainsString('code,name,client,contract_number,contract_value,status', $content);
        $this->assertStringContainsString('PRJ-RPT-01', $content);
    }

    public function test_guest_cannot_access_reports(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_finance_progress_and_assets_reports_are_reachable(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-RPT-02']);
        $this->makeApprovedBoq($actor, $project, 'BOQ-RPT-02');

        $this->actingAs($actor)->get(route('reports.finance'))->assertOk();
        $this->actingAs($actor)->get(route('reports.progress'))->assertOk()->assertSee('PRJ-RPT-02');
        $this->actingAs($actor)->get(route('reports.assets'))->assertOk();

        $this->actingAs($actor)->get(route('reports.finance.export'))->assertOk();
        $this->actingAs($actor)->get(route('reports.progress.export'))->assertOk();
        $this->actingAs($actor)->get(route('reports.assets.export'))->assertOk();
    }

    public function test_login_page_renders_branded_layout(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('PM Cost Control')
            ->assertSee('Masuk');
    }

    public function test_all_operational_roles_have_report_view(): void
    {
        foreach ([
            UserRole::ProjectAdmin,
            UserRole::BoqCostControl,
            UserRole::Approver,
            UserRole::Finance,
            UserRole::ProjectSite,
        ] as $role) {
            $user = $this->makeUserWithRole($role);
            $this->assertTrue($user->hasPermission(Permission::ReportView), $role->value.' should view reports');
        }
    }
}
