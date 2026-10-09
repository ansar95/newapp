<?php

namespace Tests\Feature;

use App\Actions\Document\RequestProjectDocumentRevision;
use App\Enums\ProjectDocumentStatus;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectDocumentVersionTest extends TestCase
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

    private function documentNeedingRevision(): ProjectDocument
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));

        return app(RequestProjectDocumentRevision::class)->handle($this->admin, $document, ['revision_reason' => 'Nilai belum sesuai.']);
    }

    public function test_revision_upload_creates_v2_and_keeps_status(): void
    {
        $projectAdmin = $this->makeProjectAdmin();
        $document = $this->documentNeedingRevision();

        $this->actingAs($projectAdmin)
            ->post(route('project-document-versions.store', $document), [
                'file' => $this->fakePdf('Invoice V2.pdf'),
                'notes' => 'Nilai disesuaikan.',
            ])
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(ProjectDocumentStatus::RevisionRequired, $document->current_status);
        $this->assertSame(2, $document->current_version);

        $version = $document->versions()->get()->last();
        $this->assertSame(2, $version->version_number);
        $this->assertSame($projectAdmin->id, $version->uploaded_by);
        $this->assertSame('Nilai disesuaikan.', $version->notes);
    }

    public function test_revision_upload_requires_notes(): void
    {
        $document = $this->documentNeedingRevision();

        $this->actingAs($this->admin)
            ->post(route('project-document-versions.store', $document), ['file' => $this->fakePdf('v2.pdf')])
            ->assertSessionHasErrors('notes');

        $this->assertSame(1, $document->fresh()->current_version);
    }

    public function test_resubmit_requires_a_new_version_and_sends_v2(): void
    {
        $document = $this->documentNeedingRevision();

        $this->actingAs($this->admin)
            ->post(route('project-documents.resubmit', $document))
            ->assertForbidden();
        $this->assertSame(ProjectDocumentStatus::RevisionRequired, $document->fresh()->current_status);

        $this->actingAs($this->admin)->post(route('project-document-versions.store', $document), [
            'file' => $this->fakePdf('v2.pdf'),
            'notes' => 'Revisi.',
        ]);

        $this->actingAs($this->admin)
            ->post(route('project-documents.resubmit', $document))
            ->assertSessionHasNoErrors();

        $document->refresh();
        $this->assertSame(ProjectDocumentStatus::Resubmitted, $document->current_status);
        $this->assertSame(2, $document->histories()->get()->last()->version_number);
    }

    public function test_old_versions_are_kept_when_revision_is_uploaded(): void
    {
        $document = $this->documentNeedingRevision();
        $v1 = $document->versions()->sole();

        $this->actingAs($this->admin)->post(route('project-document-versions.store', $document), [
            'file' => $this->fakePdf('v2.pdf'),
            'notes' => 'Revisi.',
        ]);

        $versions = $document->versions()->get();
        $this->assertSame([1, 2], $versions->pluck('version_number')->all());
        $this->assertNotSame($versions[0]->file_path, $versions[1]->file_path);
        $this->assertSame($v1->file_path, $versions[0]->fresh()->file_path);
        Storage::disk(ProjectDocumentVersion::DISK)->assertExists($versions[0]->file_path);
        Storage::disk(ProjectDocumentVersion::DISK)->assertExists($versions[1]->file_path);
    }

    public function test_only_pdf_up_to_ten_megabytes_is_accepted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('project-documents.store', $this->project), [
                'document_type_id' => $this->billingDocumentType()->id,
                'client_billing_id' => $this->billing->id,
                'document_title' => 'Dokumen',
                'file' => UploadedFile::fake()->create('invoice.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->admin)
            ->post(route('project-documents.store', $this->project), [
                'document_type_id' => $this->billingDocumentType()->id,
                'client_billing_id' => $this->billing->id,
                'document_title' => 'Dokumen',
                'file' => $this->fakePdf('besar.pdf', 10241),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ProjectDocument::query()->count());
    }

    public function test_authorized_user_can_view_and_download_without_storage_path(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing, $this->fakePdf('Invoice Termin 1.pdf'));
        $version = $document->versions()->sole();

        $download = $this->actingAs($this->makeApprover())
            ->get(route('project-document-versions.download', $version))
            ->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Invoice Termin 1.pdf', $download->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $download->headers->get('Content-Type'));

        $view = $this->actingAs($this->admin)
            ->get(route('project-document-versions.view', $version))
            ->assertOk();
        $this->assertStringContainsString('inline', $view->headers->get('Content-Disposition'));

        $this->actingAs($this->admin)
            ->get(route('project-documents.show', $document))
            ->assertOk()
            ->assertDontSee($version->file_path)
            ->assertSee(route('project-document-versions.download', $version), false);
    }

    public function test_unauthorized_users_cannot_download_documents(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);
        $version = $document->versions()->sole();

        $this->get(route('project-document-versions.download', $version))
            ->assertRedirect(route('login'));

        $this->actingAs($this->makeProjectSite())
            ->get(route('project-document-versions.download', $version))
            ->assertForbidden();

        $this->actingAs($this->makeBoqCostControl())
            ->get(route('project-document-versions.view', $version))
            ->assertForbidden();
    }
}
