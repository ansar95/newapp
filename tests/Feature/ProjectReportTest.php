<?php

namespace Tests\Feature;

use App\Enums\ProcurementType;
use App\Enums\ProjectReportSection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectReportTest extends TestCase
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
        $boq = $this->makeApprovedBoq($this->admin, $this->project);
        $this->makePaidPlanForBoq($this->admin, $boq);
    }

    public function test_every_section_renders_and_project_header_links_to_reports(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee(route('projects.reports.index', $this->project), false);

        foreach (ProjectReportSection::cases() as $section) {
            $this->actingAs($this->admin)
                ->get(route('projects.reports.index', [$this->project, 'section' => $section->value]))
                ->assertOk()
                ->assertSee('Laporan '.$section->label());
        }
    }

    public function test_project_site_only_sees_sections_allowed_by_module_permissions(): void
    {
        $site = $this->makeProjectSite();

        $this->actingAs($site)
            ->get(route('projects.reports.index', $this->project))
            ->assertOk()
            ->assertSee('Permintaan &amp; Pembelian', false)
            ->assertDontSee('Penagihan Client')
            ->assertDontSee('Total Ditagih');

        foreach ([ProjectReportSection::Billing, ProjectReportSection::Documents] as $section) {
            $this->actingAs($site)
                ->get(route('projects.reports.index', [$this->project, 'section' => $section->value]))
                ->assertForbidden();
            $this->actingAs($site)
                ->get(route('projects.reports.export', [$this->project, 'section' => $section->value]))
                ->assertForbidden();
        }

        $this->actingAs($site)
            ->get(route('projects.reports.print', [$this->project, 'section' => 'all']))
            ->assertOk()
            ->assertSee('Laporan Lengkap Project')
            ->assertSee('Biaya vs BOQ')
            ->assertDontSee('Penagihan Client');
    }

    public function test_procurement_report_filters_by_period_type_and_exports_items(): void
    {
        $paidBmhp = $this->makePaidProcurementRequest($this->admin, $this->project);
        $oldRental = $this->makeDraftProcurementRequest($this->admin, $this->project, ProcurementType::Rental, [
            $this->procurementItemPayload(ProcurementType::Rental, 1, 750000, 'Scaffolding'),
        ]);
        $oldRental->update(['request_date' => now()->subMonth()->toDateString()]);

        $this->actingAs($this->admin)
            ->get(route('projects.reports.index', [$this->project, 'section' => 'procurement', 'from' => now()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertSee($paidBmhp->request_number)
            ->assertDontSee($oldRental->request_number);

        $this->actingAs($this->admin)
            ->get(route('projects.reports.index', [$this->project, 'section' => 'procurement', 'type' => ProcurementType::Rental->value]))
            ->assertOk()
            ->assertSee($oldRental->request_number)
            ->assertDontSee($paidBmhp->request_number);

        $csv = $this->actingAs($this->admin)
            ->get(route('projects.reports.export', [$this->project, 'section' => 'procurement']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('request_number,type,request_date,status', $csv);
        $this->assertStringContainsString($paidBmhp->request_number.',BMHP', $csv);
        $this->assertStringContainsString('Gypsum 9mm', $csv);
        $this->assertStringContainsString('Scaffolding', $csv);
    }

    public function test_payment_report_respects_period_and_snapshot_sections_export_csv(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.reports.index', [$this->project, 'section' => 'payment']))
            ->assertOk()
            ->assertSee('PAY-PP-001');

        $this->actingAs($this->admin)
            ->get(route('projects.reports.index', [$this->project, 'section' => 'payment', 'from' => now()->addDay()->toDateString()]))
            ->assertOk()
            ->assertSee('Tidak ada pembayaran pada periode ini.')
            ->assertDontSee('PAY-PP-001');

        $item = $this->project->approvedBoqs()->first()->cogs()->first()->items()->first();

        foreach (['progress' => 'progress_percent', 'cost' => 'remaining_budget', 'summary' => 'Nilai Kontrak'] as $section => $expected) {
            $csv = $this->actingAs($this->admin)
                ->get(route('projects.reports.export', [$this->project, 'section' => $section]))
                ->assertOk()
                ->streamedContent();

            $this->assertStringContainsString($expected, $csv);

            if ($section !== 'summary') {
                $this->assertStringContainsString($item->code, $csv);
            }
        }
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('projects.reports.index', $this->project))
            ->get(route('projects.reports.index', [
                $this->project,
                'section' => 'procurement',
                'from' => now()->toDateString(),
                'to' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('to');

        $this->actingAs($this->admin)
            ->get(route('projects.reports.index', [$this->project, 'section' => 'procurement', 'to' => now()->toDateString()]))
            ->assertOk();
    }
}
