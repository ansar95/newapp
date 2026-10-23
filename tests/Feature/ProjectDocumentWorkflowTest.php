<?php

namespace Tests\Feature;

use App\Actions\Document\ApproveProjectDocument;
use App\Actions\Document\RequestProjectDocumentRevision;
use App\Actions\Document\TransitionProjectDocument;
use App\Actions\Document\UploadProjectDocumentVersion;
use App\Enums\ProjectDocumentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectDocumentWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    private ClientBilling $billing;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProjectDocumentVersion::DISK);

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin, ['contract_value' => 1_000_000_000]);
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $this->billing = $this->makeSentBilling($this->admin, $term, 300_000_000);
    }

    public function test_project_admin_can_create_document_with_first_version(): void
    {
        $projectAdmin = $this->makeProjectAdmin();

        $response = $this->actingAs($projectAdmin)
            ->post(route('project-documents.store', $this->project), [
                'type_label' => 'Kontrak',
                'document_title' => 'Dokumen Penagihan Termin 1',
                'file' => $this->fakePdf('../Invoice <Termin> 1.pdf'),
            ])
            ->assertSessionHasNoErrors();

        $document = ProjectDocument::query()->sole();
        $response->assertRedirect(route('project-documents.show', $document));

        $this->assertSame('DOC-0001', $document->document_number);
        $this->assertSame('Kontrak', $document->type_label);
        $this->assertNull($document->reference_type);
        $this->assertNull($document->reference_id);
        $this->assertSame(ProjectDocumentStatus::Archived, $document->current_status);
        $this->assertSame(1, $document->current_version);
        $this->assertSame($projectAdmin->id, $document->created_by);

        $version = $document->versions()->sole();
        $this->assertSame(1, $version->version_number);
        $this->assertSame($projectAdmin->id, $version->uploaded_by);
        $this->assertSame('Invoice _Termin_ 1.pdf', $version->original_filename);
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertStringNotContainsString('Invoice', $version->file_path);
        $this->assertMatchesRegularExpression('#^project-documents/\d+/\d+/[0-9a-f-]{36}\.pdf$#', $version->file_path);
        Storage::disk(ProjectDocumentVersion::DISK)->assertExists($version->file_path);
    }

    public function test_draft_document_can_be_submitted(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);

        $this->actingAs($this->admin)
            ->post(route('project-documents.submit', $document))
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectDocumentStatus::Submitted, $document->fresh()->current_status);
    }

    public function test_submitted_document_can_start_review(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);
        $this->actingAs($this->admin)->post(route('project-documents.submit', $document));

        $this->actingAs($this->admin)
            ->post(route('project-documents.start-review', $document))
            ->assertSessionHasNoErrors();

        $this->assertSame(ProjectDocumentStatus::UnderReview, $document->fresh()->current_status);
    }

    public function test_document_under_review_can_be_approved(): void
    {
        $finance = $this->makeFinance();
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));

        $this->actingAs($finance)
            ->post(route('project-documents.approve', $document), ['approval_notes' => 'Disetujui via email client.'])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(ProjectDocumentStatus::Approved, $document->current_status);
        $this->assertSame($finance->id, $document->approved_by);
        $this->assertNotNull($document->approved_at);
        $this->assertSame('Disetujui via email client.', $document->approval_notes);
        $this->assertSame(1, $document->approved_version);
    }

    public function test_document_under_review_can_be_sent_back_for_revision(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));

        $this->actingAs($this->admin)
            ->post(route('project-documents.request-revision', $document), ['revision_reason' => 'Nomor kontrak belum dicantumkan.'])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(ProjectDocumentStatus::RevisionRequired, $document->current_status);
        $this->assertSame('Nomor kontrak belum dicantumkan.', $document->histories()->get()->last()->revision_reason);
    }

    public function test_revision_requires_a_reason(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));

        $this->actingAs($this->admin)
            ->post(route('project-documents.request-revision', $document), ['revision_reason' => ''])
            ->assertSessionHasErrors('revision_reason');

        $this->assertSame(ProjectDocumentStatus::UnderReview, $document->fresh()->current_status);
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);

        $this->actingAs($this->admin)
            ->post(route('project-documents.approve', $document))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('project-documents.start-review', $document))
            ->assertForbidden();

        $this->expectException(BusinessRuleException::class);

        try {
            app(ApproveProjectDocument::class)->handle($this->admin, $document);
        } finally {
            $this->assertSame(ProjectDocumentStatus::Draft, $document->fresh()->current_status);
            $this->assertSame(1, $document->histories()->count());
        }
    }

    public function test_approved_document_cannot_change_status_or_be_edited(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));
        app(ApproveProjectDocument::class)->handle($this->admin, $document);

        $this->actingAs($this->admin)
            ->put(route('project-documents.update', $document), ['document_title' => 'Ubah judul'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('project-document-versions.store', $document), ['file' => $this->fakePdf()])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post(route('project-documents.cancel', $document), ['cancel_reason' => 'Batal'])
            ->assertForbidden();

        foreach ([ProjectDocumentStatus::Draft, ProjectDocumentStatus::RevisionRequired] as $target) {
            try {
                app(TransitionProjectDocument::class)->handle($this->admin, $document, $target);
                $this->fail('APPROVED → '.$target->value.' must be rejected.');
            } catch (BusinessRuleException) {
                // expected
            }
        }

        try {
            app(UploadProjectDocumentVersion::class)->handle($this->admin, $document, [], $this->fakePdf());
            $this->fail('Approved document must not accept a new version.');
        } catch (BusinessRuleException) {
            // expected
        }

        $document->refresh();
        $this->assertSame(ProjectDocumentStatus::Approved, $document->current_status);
        $this->assertSame(1, $document->current_version);
        $this->assertSame(1, $document->versions()->count());
    }

    public function test_every_status_change_is_recorded_in_history(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));
        app(RequestProjectDocumentRevision::class)->handle($this->admin, $document, ['revision_reason' => 'Perbaiki lampiran.']);
        app(UploadProjectDocumentVersion::class)->handle($this->admin, $document->fresh(), ['notes' => 'Lampiran diperbaiki.'], $this->fakePdf('v2.pdf'));
        $this->actingAs($this->admin)->post(route('project-documents.resubmit', $document))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('project-documents.start-review', $document))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('project-documents.approve', $document))->assertSessionHasNoErrors();

        $histories = $document->histories()->get();

        $this->assertSame(
            [null, 'DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'REVISION_REQUIRED', 'RESUBMITTED', 'UNDER_REVIEW'],
            $histories->map(fn ($history) => $history->from_status?->value)->all()
        );
        $this->assertSame(
            ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'REVISION_REQUIRED', 'RESUBMITTED', 'UNDER_REVIEW', 'APPROVED'],
            $histories->map(fn ($history) => $history->to_status->value)->all()
        );
        $this->assertSame([1, 1, 1, 1, 2, 2, 2], $histories->pluck('version_number')->all());
        $this->assertTrue($histories->every(fn ($history) => $history->changed_by === $this->admin->id && $history->changed_at !== null));
        $this->assertTrue($histories->every(fn ($history) => $history->actor_type->value === 'INTERNAL'));
    }

    public function test_cancel_is_only_allowed_before_review_and_requires_reason(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);

        $this->actingAs($this->admin)
            ->post(route('project-documents.cancel', $document), ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($this->admin)
            ->post(route('project-documents.cancel', $document), ['cancel_reason' => 'Salah upload.'])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(ProjectDocumentStatus::Cancelled, $document->current_status);
        $this->assertSame('Salah upload.', $document->cancel_reason);

        $underReview = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));

        $this->actingAs($this->admin)
            ->post(route('project-documents.cancel', $underReview), ['cancel_reason' => 'Batal.'])
            ->assertForbidden();
    }
}
