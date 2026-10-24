<?php

namespace Tests\Feature;

use App\Actions\BOQ\ConfirmBOQ;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\Progress\UpdateItemProgress;
use App\Actions\SCurve\EstablishSCurveFromItems;
use App\Actions\SCurve\PrepareSCurveBaseline;
use App\Actions\SCurve\SaveSCurveBaseline;
use App\Enums\ProjectStatus;
use App\Enums\SCurveBaselineStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Exceptions\BusinessRuleException;
use App\Models\ProgressSnapshot;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Models\User;
use App\Support\SCurvePlanSheet;
use App\Support\SCurveSeries;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class SCurveTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-22 09:00:00');
    }

    public function test_progress_update_records_snapshot_that_matches_project_progress(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-SNAP');
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-SNAP');
        $item = $boq->cogs->first()->items->first();

        app(UpdateItemProgress::class)->handle($actor, $item, 40);

        $project->refresh();
        $snapshot = ProgressSnapshot::query()->where('project_id', $project->id)->sole();

        $this->assertSame('2026-10-22', $snapshot->snapshot_date->toDateString());
        $this->assertEqualsWithDelta($project->progressPercentage(), (float) $snapshot->progress_percentage, 0.001);

        app(UpdateItemProgress::class)->handle($actor, $item->fresh(), 55);
        $project->refresh();

        $this->assertSame(1, ProgressSnapshot::query()->where('project_id', $project->id)->count());
        $this->assertEqualsWithDelta($project->progressPercentage(), (float) $snapshot->fresh()->progress_percentage, 0.001);
        $this->assertSame(0, SCurveBaseline::query()->count());
    }

    public function test_weekly_plan_cumulative_actual_and_deviation(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-CURVE');
        $initial = $this->establish($actor, $project, SCurveKind::Initial, [25, 25, 25, 25], 'Rencana awal');
        $rebaseline = $this->establish($actor, $project, SCurveKind::Rebaseline, [10, 20, 30, 40], 'Jadwal direvisi');

        ProgressSnapshot::factory()->create([
            'project_id' => $project->id,
            'snapshot_date' => '2026-10-15',
            'progress_percentage' => 20,
        ]);
        ProgressSnapshot::factory()->create([
            'project_id' => $project->id,
            'snapshot_date' => '2026-10-22',
            'progress_percentage' => 40,
        ]);

        $series = SCurveSeries::for($project->fresh(), SCurveGranularity::Week);
        $points = collect($series['points'])->keyBy('period_start');

        $this->assertSame(1, $initial->version);
        $this->assertSame(2, $rebaseline->version);
        $this->assertSame(SCurveBaselineStatus::Established, $initial->fresh()->status);
        $this->assertCount(4, $series['points']);
        $this->assertSame([25.0, 50.0, 75.0, 100.0], $points->pluck('p0')->all());
        $this->assertSame([10.0, 30.0, 60.0, 100.0], $points->pluck('p1')->all());
        $this->assertNull($points['2026-10-05']['actual']);
        $this->assertSame(20.0, $points['2026-10-12']['actual']);
        $this->assertSame(40.0, $points['2026-10-19']['actual']);
        $this->assertSame('snapshot', $points['2026-10-19']['actual_source']);
        $this->assertNull($points['2026-10-26']['actual']);
        $this->assertSame(-20.0, $points['2026-10-19']['deviation']);
        $this->assertSame(-20.0, $series['summary']['deviation']);
        $this->assertSame('in_progress', $points['2026-10-19']['timing']);
        $this->assertSame('not_started', $points['2026-10-26']['timing']);
    }

    public function test_monthly_view_sums_weekly_plan_and_monthly_plan_has_no_fake_weeks(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-MONTH');
        $this->establish($actor, $project, SCurveKind::Initial, [25, 25, 25, 25]);

        $monthly = SCurveSeries::for($project->fresh(), SCurveGranularity::Month);

        $this->assertSame([75.0, 100.0], collect($monthly['points'])->pluck('p0')->all());
        $this->assertSame(['01/10/2026', '01/11/2026'], collect($monthly['points'])->pluck('label')->all());

        $other = $this->datedProject($actor, 'PRJ-MON2');
        $this->establish($actor, $other, SCurveKind::Initial, [60, 40], 'Bulanan', SCurveGranularity::Month);

        $weekly = SCurveSeries::for($other->fresh(), SCurveGranularity::Week);

        $this->assertFalse($weekly['weekly_available']);
        $this->assertSame([], $weekly['points']);
        $this->assertTrue(collect($weekly['notices'])->contains(fn (string $notice) => str_contains($notice, 'per bulan')));
    }

    public function test_open_period_without_snapshot_uses_live_progress_and_past_stays_empty(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-LIVE');
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-LIVE');
        $beforePlan = SCurveSeries::for($project->fresh(), SCurveGranularity::Week);

        $this->assertSame([], $beforePlan['points']);
        $this->assertSame(0.0, $beforePlan['summary']['actual']);
        $this->assertNull($beforePlan['summary']['p0']);

        $this->establish($actor, $project, SCurveKind::Initial, [25, 25, 25, 25]);
        $item = $boq->cogs->first()->items->first();
        $item->progress_percentage = 80;
        $item->save();

        $series = SCurveSeries::for($project->fresh(), SCurveGranularity::Week);
        $points = collect($series['points'])->keyBy('period_start');
        $expected = $project->fresh()->progressPercentage();

        $this->assertNull($points['2026-10-05']['actual']);
        $this->assertSame($expected, $points['2026-10-19']['actual']);
        $this->assertSame('live', $points['2026-10-19']['actual_source']);
        $this->assertNull($points['2026-10-26']['actual']);
        $this->assertSame(25.0, (float) $series['initial']->periods->first()->planned_percent);
    }

    public function test_project_without_schedule_or_boq_does_not_invent_a_plan(): void
    {
        $actor = $this->makeSuperadmin();
        $unscheduled = Project::factory()->create([
            'code' => 'PRJ-NODATE',
            'start_date' => null,
            'target_end_date' => null,
            'created_by' => $actor->id,
        ]);

        try {
            app(PrepareSCurveBaseline::class)->handle($actor, $unscheduled, SCurveKind::Initial, SCurveGranularity::Week);
            $this->fail('Rencana tanpa tanggal proyek tidak boleh dibuat.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString('Tanggal mulai', $exception->getMessage());
        }

        $project = $this->datedProject($actor, 'PRJ-NOBOQ');
        $this->establish($actor, $project, SCurveKind::Initial, [25, 25, 25, 25]);
        $series = SCurveSeries::for($project->fresh(), SCurveGranularity::Week);
        $current = collect($series['points'])->firstWhere('timing', 'in_progress');

        $this->assertSame(0.0, $current['actual']);
        $this->assertSame(75.0, $current['p0']);
        $this->assertTrue(collect($series['notices'])->contains(fn (string $notice) => str_contains($notice, 'BOQ')));
    }

    public function test_rebaseline_keeps_initial_plan_and_rejects_incomplete_total(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-REV');
        $initial = $this->establish($actor, $project, SCurveKind::Initial, [25, 25, 25, 25], 'Tetap');
        $draft = app(PrepareSCurveBaseline::class)->handle($actor, $project->fresh(), SCurveKind::Rebaseline, null, 'Revisi');

        $this->assertSame(
            $initial->periods->map(fn ($period) => (float) $period->planned_percent)->all(),
            $draft->periods->map(fn ($period) => (float) $period->planned_percent)->all(),
        );

        $incomplete = $draft->periods->mapWithKeys(fn ($period) => [$period->id => 10])->all();

        $this->actingAs($actor)
            ->put(route('s-curve-baselines.update', $draft), [
                'periods' => $incomplete,
                'establish' => '1',
                'return_to' => 'project',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'periods' => 'Jumlah rencana harus 100%. Saat ini 40.00%.',
            ]);

        $this->assertSame(SCurveBaselineStatus::Established, $initial->fresh()->status);
        $this->assertSame(SCurveBaselineStatus::Draft, $draft->fresh()->status);

        $this->actingAs($actor)
            ->put(route('s-curve-baselines.update', $initial), [
                'periods' => $initial->periods->mapWithKeys(fn ($period) => [$period->id => 25])->all(),
                'establish' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'business' => 'Rencana yang sudah ditetapkan tidak dapat diubah. Buat rebaseline untuk versi baru.',
            ]);

        $this->assertSame('Tetap', $initial->fresh()->reason);
    }

    public function test_curves_stay_isolated_between_projects(): void
    {
        $actor = $this->makeSuperadmin();
        $first = $this->datedProject($actor, 'PRJ-A');
        $second = $this->datedProject($actor, 'PRJ-B');
        $this->establish($actor, $first, SCurveKind::Initial, [25, 25, 25, 25], 'Alasan khusus proyek A');

        $series = SCurveSeries::for($second->fresh(), SCurveGranularity::Week);

        $this->assertNull($series['initial']);
        $this->assertSame([], $series['points']);

        $this->actingAs($actor)
            ->get(route('projects.s-curve.show', $second))
            ->assertOk()
            ->assertDontSee('Alasan khusus proyek A');
    }

    public function test_menu_project_page_and_import_follow_existing_permissions(): void
    {
        $owner = $this->makeSuperadmin();
        $admin = $this->makeBoqCostControl();
        $site = $this->makeProjectSite();
        $project = $this->datedProject($owner, 'PRJ-HTTP');

        $this->get(route('s-curve.index'))->assertRedirect(route('login'));

        $this->actingAs($site)
            ->get(route('s-curve.index', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Kurva S')
            ->assertSee('PRJ-HTTP')
            ->assertDontSee('Tetapkan rencana awal');

        $this->actingAs($site)
            ->post(route('projects.s-curve.plan.store', $project), [
                'kind' => 'initial',
                'items' => [],
            ])
            ->assertForbidden();

        $this->actingAs($site)
            ->get(route('projects.s-curve.plan.template', $project))
            ->assertForbidden();

        $this->actingAs($site)
            ->post(route('projects.s-curve.plan.import', $project), ['kind' => 'initial'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee('Kurva S')
            ->assertSee('Langkah berikutnya: susun BOQ');

        $boq = $this->makeApprovedBoq($owner, $project, 'BOQ-HTTP');
        $items = [];

        foreach ($boq->cogs->flatMap->items as $item) {
            $items[$item->id] = ['start' => '2026-10-05', 'finish' => '2026-10-25'];
        }

        $this->actingAs($admin)
            ->get(route('projects.s-curve.show', $project))
            ->assertOk()
            ->assertSee('Tetapkan rencana awal')
            ->assertSee('Download template Excel')
            ->assertSee($boq->cogs->first()->items->first()->name);

        $this->actingAs($admin)
            ->post(route('projects.s-curve.plan.store', $project), [
                'kind' => 'initial',
                'return_to' => 'project',
                'items' => $items,
            ])
            ->assertRedirect(route('projects.s-curve.show', $project))
            ->assertSessionHasNoErrors();

        $this->assertSame(SCurveBaselineStatus::Established, SCurveBaseline::query()->where('project_id', $project->id)->sole()->status);

        $this->actingAs($admin)
            ->get(route('projects.s-curve.show', $project))
            ->assertOk()
            ->assertSee('Rencana awal kumulatif')
            ->assertSee('Deviasi');
    }

    public function test_item_dates_build_the_initial_plan_and_progress_only_changes_actual(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-ITEM');
        $boq = $this->makeDraftBoq($actor, $project, [[
            'code' => 'COGS-001',
            'name' => 'Pekerjaan',
            'description' => 'Pekerjaan',
            'sort_order' => 1,
            'items' => [
                ['code' => 'ITEM-A', 'name' => 'Pekerjaan A', 'quantity' => 1, 'unit' => 'ls', 'unit_price' => 5000000, 'sort_order' => 1],
                ['code' => 'ITEM-B', 'name' => 'Pekerjaan B', 'quantity' => 1, 'unit' => 'ls', 'unit_price' => 5000000, 'sort_order' => 2],
            ],
        ]], 'BOQ-ITEM');
        app(SubmitBOQ::class)->handle($actor, $boq);
        app(ConfirmBOQ::class)->handle($actor, $boq->fresh(), 'Approved');
        $items = $boq->fresh('cogs.items')->cogs->flatMap->items->keyBy('code');

        $this->actingAs($actor)
            ->post(route('projects.s-curve.plan.store', $project), [
                'kind' => 'initial',
                'items' => [
                    $items['ITEM-A']->id => ['start' => '2026-10-05', 'finish' => '2026-10-11'],
                ],
            ])
            ->assertSessionHasErrors([
                'items' => 'Setiap item BOQ wajib diisi tanggal mulai dan selesai.',
            ]);

        $baseline = app(EstablishSCurveFromItems::class)->handle($actor, $project->fresh(), SCurveKind::Initial, [
            $items['ITEM-A']->id => ['start' => '2026-10-05', 'finish' => '2026-10-11'],
            $items['ITEM-B']->id => ['start' => '2026-10-12', 'finish' => '2026-10-25'],
        ]);

        $planned = $baseline->periods->values()->map(fn ($period) => (float) $period->planned_percent)->all();

        $this->assertCount(3, $planned);
        $this->assertEqualsWithDelta(50, $planned[0], 0.05);
        $this->assertEqualsWithDelta(25, $planned[1], 0.05);
        $this->assertEqualsWithDelta(25, $planned[2], 0.05);
        $this->assertEqualsWithDelta(100, array_sum($planned), 0.05);

        app(UpdateItemProgress::class)->handle($actor, $items['ITEM-A'], 100);

        $this->assertEqualsWithDelta($planned[0], (float) $baseline->periods()->orderBy('period_start')->first()->planned_percent, 0.001);

        $series = SCurveSeries::for($project->fresh(), SCurveGranularity::Week);
        $points = collect($series['points'])->keyBy('period_start');

        $this->assertNull($points['2026-10-05']['actual']);
        $this->assertSame($project->fresh()->progressPercentage(), $points['2026-10-19']['actual']);
        $this->assertSame(100.0, $points['2026-10-19']['p0']);
    }

    public function test_excel_template_imports_item_dates_for_the_plan(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->datedProject($actor, 'PRJ-XLS');
        $boq = $this->makeDraftBoq($actor, $project, [[
            'code' => 'COGS-001',
            'name' => 'Pekerjaan',
            'description' => 'Pekerjaan',
            'sort_order' => 1,
            'items' => [
                ['code' => 'ITEM-A', 'name' => 'Pekerjaan A', 'quantity' => 1, 'unit' => 'ls', 'unit_price' => 5000000, 'sort_order' => 1],
                ['code' => 'ITEM-B', 'name' => 'Pekerjaan B', 'quantity' => 1, 'unit' => 'ls', 'unit_price' => 5000000, 'sort_order' => 2],
            ],
        ]], 'BOQ-XLS');
        app(SubmitBOQ::class)->handle($actor, $boq);
        app(ConfirmBOQ::class)->handle($actor, $boq->fresh(), 'Approved');

        $this->get(route('projects.s-curve.plan.template', $project))->assertRedirect(route('login'));

        $download = $this->actingAs($actor)->get(route('projects.s-curve.plan.template', $project));
        $download->assertOk();
        $download->assertHeader('content-type', SimpleXlsx::MIME);
        $rows = SimpleXlsx::readString($download->streamedContent());

        $this->assertSame(SCurvePlanSheet::HEADERS, $rows[0]);
        $this->assertSame(['ITEM-A', 'Pekerjaan', 'Pekerjaan A'], $rows[1]);
        $this->assertSame(['ITEM-B', 'Pekerjaan', 'Pekerjaan B'], $rows[2]);

        $this->actingAs($actor)
            ->post(route('projects.s-curve.plan.import', $project), [
                'kind' => 'initial',
                'return_to' => 'project',
                'file' => UploadedFile::fake()->createWithContent('rencana.xlsx', SimpleXlsx::write([
                    ['item_code'],
                ])),
            ])
            ->assertSessionHasErrors(['file' => 'Kolom wajib hilang: planned_start. Gunakan template yang disediakan.']);

        $this->actingAs($actor)
            ->post(route('projects.s-curve.plan.import', $project), [
                'kind' => 'initial',
                'file' => UploadedFile::fake()->createWithContent('rencana.xlsx', SimpleXlsx::write([
                    SCurvePlanSheet::HEADERS,
                    ['ITEM-X', 'Pekerjaan', 'Lain', '05/10/2026', '11/10/2026'],
                ])),
            ])
            ->assertSessionHasErrors(['file' => 'Baris 2: kode item ITEM-X tidak ada pada BOQ approved.']);

        $rows[1][3] = '05/10/2026';
        $rows[1][4] = '11/10/2026';
        $rows[2][3] = '12/10/2026';
        $rows[2][4] = '25/10/2026';

        $this->actingAs($actor)
            ->post(route('projects.s-curve.plan.import', $project), [
                'kind' => 'initial',
                'return_to' => 'project',
                'file' => UploadedFile::fake()->createWithContent('rencana.xlsx', SimpleXlsx::write($rows)),
            ])
            ->assertRedirect(route('projects.s-curve.show', $project))
            ->assertSessionHasNoErrors();

        $baseline = SCurveBaseline::query()->where('project_id', $project->id)->sole();
        $planned = $baseline->periods->values()->map(fn ($period) => (float) $period->planned_percent)->all();

        $this->assertSame(SCurveBaselineStatus::Established, $baseline->status);
        $this->assertCount(3, $planned);
        $this->assertEqualsWithDelta(100, array_sum($planned), 0.05);

        $again = SimpleXlsx::readString($this->actingAs($actor)
            ->get(route('projects.s-curve.plan.template', $project))
            ->streamedContent());

        $this->assertSame('05/10/2026', $again[1][3]);
        $this->assertSame('25/10/2026', $again[2][4]);
    }

    public function test_dashboard_keeps_existing_sections_and_adds_one_project_curve(): void
    {
        $actor = $this->makeSuperadmin();
        $this->travelTo('2026-10-05 13:00:00');
        $ending = Project::factory()->create([
            'code' => 'PRJ-SOON',
            'name' => 'Kontrak Dekat',
            'status' => ProjectStatus::Active,
            'start_date' => '2026-09-01',
            'target_end_date' => '2026-10-10',
            'created_by' => $actor->id,
        ]);
        $curve = $this->datedProject($actor, 'PRJ-DASH');
        $this->establish($actor, $curve, SCurveKind::Initial, [25, 25, 25, 25]);

        $this->actingAs($actor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Peringatan Kontrak')
            ->assertSee('Total Projects')
            ->assertSee('Recent Projects')
            ->assertSee('Project Progress')
            ->assertSee('Kurva S')
            ->assertSee($ending->code);

        $this->actingAs($actor)
            ->get(route('dashboard', ['kurva' => $curve->id]))
            ->assertOk()
            ->assertSee('Peringatan Kontrak')
            ->assertSee('Recent Projects')
            ->assertSee('Project Progress')
            ->assertSee('Rencana awal')
            ->assertSee('PRJ-DASH')
            ->assertSee($ending->name);
    }

    private function datedProject(User $actor, string $code): Project
    {
        return $this->makeActiveProject($actor, [
            'code' => $code,
            'name' => 'Proyek '.$code,
            'start_date' => '2026-10-05',
            'target_end_date' => '2026-11-01',
        ]);
    }

    /**
     * @param  list<float>  $percents
     */
    private function establish(
        User $actor,
        Project $project,
        SCurveKind $kind,
        array $percents,
        ?string $reason = null,
        SCurveGranularity $granularity = SCurveGranularity::Week,
    ): SCurveBaseline {
        $draft = app(PrepareSCurveBaseline::class)->handle(
            $actor,
            $project->fresh(),
            $kind,
            $kind === SCurveKind::Initial ? $granularity : null,
            $reason,
        );

        $values = [];

        foreach ($draft->periods->values() as $index => $period) {
            $values[$period->id] = $percents[$index];
        }

        return app(SaveSCurveBaseline::class)->handle($actor, $draft, $values, $reason, true);
    }
}
