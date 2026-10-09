<?php

namespace Tests\Feature;

use App\Actions\Document\ApproveProjectDocument;
use App\Actions\Document\RequestProjectDocumentRevision;
use App\Actions\Document\ResubmitProjectDocument;
use App\Actions\Document\StartProjectDocumentReview;
use App\Actions\Document\SubmitProjectDocument;
use App\Actions\Document\UploadProjectDocumentVersion;
use App\Enums\ProjectDocumentStatus;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectDocumentVersion;
use App\Models\ProjectTerm;
use App\Models\User;
use App\Support\ProjectDetailContext;
use App\Support\ProjectDocumentMonitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectDocumentMonitoringTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private ProjectTerm $term;

    private ClientBilling $billing;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProjectDocumentVersion::DISK);

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin, ['contract_value' => 1_000_000_000]);
        $this->term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->billing = $this->makeSentBilling($this->admin, $this->term, 300_000_000);
    }

    public function test_timeline_lists_uploads_and_status_changes_newest_first(): void
    {
        $start = Carbon::parse('2026-09-01 08:00:00');

        $this->travelTo($start);
        $document = $this->makeBillingDocument($this->admin, $this->billing);
        $this->travelTo($start->copy()->addDay());
        app(SubmitProjectDocument::class)->handle($this->admin, $document->fresh());
        $this->travelTo($start->copy()->addDays(2));
        app(StartProjectDocumentReview::class)->handle($this->admin, $document->fresh());
        $this->travelTo($start->copy()->addDays(3));
        app(RequestProjectDocumentRevision::class)->handle($this->admin, $document->fresh(), ['revision_reason' => 'Perbaiki nilai.']);
        $this->travelTo($start->copy()->addDays(4));
        app(UploadProjectDocumentVersion::class)->handle($this->admin, $document->fresh(), ['notes' => 'Nilai diperbaiki.'], $this->fakePdf('v2.pdf'));
        app(ResubmitProjectDocument::class)->handle($this->admin, $document->fresh());

        $timeline = ProjectDocumentMonitor::timeline($document->fresh(), $start->copy()->addDays(5));

        $this->assertSame([
            'status:RESUBMITTED',
            'upload:2',
            'status:REVISION_REQUIRED',
            'status:UNDER_REVIEW',
            'status:SUBMITTED',
            'upload:1',
            'status:DRAFT',
        ], $timeline->map(fn (array $event) => $event['type'] === 'upload'
            ? 'upload:'.$event['version_number']
            : 'status:'.$event['status']->value)->all());

        $this->assertSame('Perbaiki nilai.', $timeline[2]['revision_reason']);
        $this->assertSame(86400, $timeline[2]['duration_seconds']);
        $this->assertTrue($timeline[0]['ongoing']);
        $this->assertSame($this->admin->name, $timeline[1]['actor']);

        $this->actingAs($this->admin)
            ->get(route('project-documents.show', $document))
            ->assertOk()
            ->assertSee('Document Timeline')
            ->assertSeeInOrder(['Upload V2', 'Perbaiki nilai.', 'Upload V1']);
    }

    public function test_age_and_status_durations_are_calculated_from_history(): void
    {
        $start = Carbon::parse('2026-09-01 08:00:00');

        $this->travelTo($start);
        $document = $this->makeBillingDocument($this->admin, $this->billing);
        $this->travelTo($start->copy()->addHours(5));
        app(SubmitProjectDocument::class)->handle($this->admin, $document->fresh());
        $this->travelTo($start->copy()->addHours(5)->addDays(2));
        app(StartProjectDocumentReview::class)->handle($this->admin, $document->fresh());

        $now = $start->copy()->addHours(5)->addDays(5)->addHours(3);
        $document = $document->fresh(['histories', 'versions']);

        $this->assertSame(3 * 86400 + 3 * 3600, $document->waitingSeconds($now));
        $this->assertSame('3 hari', ProjectDocumentMonitor::formatDuration($document->waitingSeconds($now)));
        $this->assertSame('5 jam', ProjectDocumentMonitor::formatDuration(5 * 3600));
        $this->assertSame('< 1 jam', ProjectDocumentMonitor::formatDuration(600));
        $this->assertSame([
            'DRAFT' => 5 * 3600,
            'SUBMITTED' => 2 * 86400,
            'UNDER_REVIEW' => 3 * 86400 + 3 * 3600,
        ], ProjectDocumentMonitor::totalSecondsByStatus($document, $now));
        $this->assertSame(5 * 86400 + 3 * 3600, ProjectDocumentMonitor::cycleSeconds($document, $now));

        $this->travelTo($now);
        app(ApproveProjectDocument::class)->handle($this->admin, $document);
        $approved = $document->fresh(['histories', 'versions']);

        $this->assertNull($approved->waitingSeconds($now->copy()->addDays(10)));
        $this->assertSame(5 * 86400 + 3 * 3600, ProjectDocumentMonitor::cycleSeconds($approved, $now->copy()->addDays(10)));
    }

    public function test_monitoring_page_shows_summary_and_indicators(): void
    {
        $approved = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));
        app(ApproveProjectDocument::class)->handle($this->admin, $approved);

        $secondTerm = $this->makeTerm($this->admin, $this->project, ['term_name' => 'Progress', 'percentage' => 30]);
        $revision = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->makeSentBilling($this->admin, $secondTerm, 100_000_000)));
        app(RequestProjectDocumentRevision::class)->handle($this->admin, $revision, ['revision_reason' => 'Revisi.']);

        $waiting = $this->makeBillingDocument($this->admin, $this->makeSentBilling($this->admin, $secondTerm, 50_000_000));
        app(SubmitProjectDocument::class)->handle($this->admin, $waiting);

        $monitor = ProjectDocumentMonitor::forProject($this->project);
        $this->assertSame(3, $monitor->total());
        $this->assertSame(1, $monitor->submittedCount());
        $this->assertSame(0, $monitor->underReviewCount());
        $this->assertSame(1, $monitor->revisionCount());
        $this->assertSame(1, $monitor->approvedCount());
        $this->assertSame(1, $monitor->waitingClientCount());
        $this->assertSame(1, $monitor->waitingAdminCount());
        $this->assertSame(1, $monitor->completedCount());

        $this->actingAs($this->admin)
            ->get(route('projects.documents.index', $this->project))
            ->assertOk()
            ->assertSee('Monitoring Dokumen')
            ->assertSee('Pantau seluruh dokumen project dan status review client.')
            ->assertSee('+ Upload Document')
            ->assertSeeInOrder(['Document', 'Type / Reference', 'Version', 'Status', 'Submitted Date', 'Last Update', 'Age / Waiting', 'Action'])
            ->assertSee('Waiting Admin')
            ->assertSee('Waiting Client')
            ->assertSee('Completed');
    }

    public function test_dokumen_tab_sits_between_penagihan_and_payment(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.documents.index', $this->project))
            ->assertOk()
            ->assertSeeInOrder([
                route('projects.show', $this->project),
                route('projects.boq.index', $this->project),
                route('projects.assets.index', $this->project),
                route('projects.bmhp.index', $this->project),
                route('projects.billing.index', $this->project),
                route('projects.documents.index', $this->project),
                route('projects.payment.index', $this->project),
                route('projects.progress.index', $this->project),
            ], false);
    }

    public function test_overview_shows_document_status_without_replacing_next_action(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));
        app(RequestProjectDocumentRevision::class)->handle($this->admin, $document, ['revision_reason' => 'Revisi.']);

        $response = $this->actingAs($this->admin)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Document Status')
            ->assertSee('1 dokumen membutuhkan revisi.')
            ->assertSee('Review Documents');

        $nextAction = ProjectDetailContext::for($this->project, $this->admin)->nextAction;
        $response->assertSeeInOrder(['Next Action', $nextAction['message'], 'Review Documents'], false);
    }

    public function test_document_permissions_follow_roles(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);

        $this->actingAs($this->makeApprover())
            ->get(route('projects.documents.index', $this->project))
            ->assertOk()
            ->assertDontSee('+ Upload Document');

        $this->actingAs($this->makeApprover())
            ->post(route('project-documents.submit', $document))
            ->assertForbidden();

        $this->actingAs($this->makeBoqCostControl())
            ->get(route('project-documents.show', $document))
            ->assertOk();

        $this->actingAs($this->makeProjectSite())
            ->get(route('projects.documents.index', $this->project))
            ->assertForbidden();

        $this->actingAs($this->makeProjectSite())
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertDontSee(route('projects.documents.index', $this->project), false);

        $this->actingAs($this->makeFinance())
            ->get(route('project-documents.create', $this->project))
            ->assertOk();

        $this->assertSame(ProjectDocumentStatus::Draft, $document->fresh()->current_status);
    }
}
