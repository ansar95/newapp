<?php

namespace Tests\Feature;

use App\Actions\Document\ApproveProjectDocument;
use App\Actions\Document\CreateProjectDocument;
use App\Enums\ClientBillingStatus;
use App\Enums\ClientPaymentStatus;
use App\Enums\DocumentReferenceType;
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

class ProjectDocumentReferenceTest extends TestCase
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
        $this->billing = $this->makeSentBilling($this->admin, $term, 300_000_000, 'INV/2026/001');
    }

    public function test_document_is_linked_to_billing(): void
    {
        $document = $this->makeBillingDocument($this->admin, $this->billing);

        $this->assertSame(DocumentReferenceType::Billing->value, $document->reference_type);
        $this->assertSame($this->billing->id, $document->reference_id);
        $this->assertTrue($document->billing()->is($this->billing));
        $this->assertTrue($this->billing->documents()->sole()->is($document));

        $this->actingAs($this->admin)
            ->get(route('client-billings.show', $this->billing))
            ->assertOk()
            ->assertSee('Lampiran Internal')
            ->assertSee($document->document_title)
            ->assertDontSee('+ Unggah Dokumen');
    }

    public function test_upload_form_stores_a_free_type_without_a_billing(): void
    {
        $this->actingAs($this->admin)
            ->get(route('project-documents.create', $this->project))
            ->assertOk()
            ->assertSee('name="type_label"', false)
            ->assertDontSee('name="client_billing_id"', false)
            ->assertDontSee('name="document_type_id"', false)
            ->assertDontSee('Penagihan');

        $this->actingAs($this->admin)
            ->post(route('project-documents.store', $this->project), [
                'type_label' => '  Berita Acara  ',
                'document_title' => 'BA Progress',
                'file' => $this->fakePdf(),
            ])
            ->assertSessionHasNoErrors();

        $document = ProjectDocument::query()->sole();
        $this->assertSame('Berita Acara', $document->type_label);
        $this->assertSame('Berita Acara', $document->typeName());
        $this->assertNull($document->reference_type);
        $this->assertNull($document->reference_id);
        $this->assertSame(ProjectDocumentStatus::Archived, $document->current_status);
    }

    public function test_billing_from_another_project_cannot_be_linked(): void
    {
        $otherProject = $this->makeActiveProject($this->admin, ['code' => 'PRJ-OTHER', 'contract_value' => 500_000_000]);
        $otherTerm = $this->makeTerm($this->admin, $otherProject, ['percentage' => 50]);
        $otherBilling = $this->makeSentBilling($this->admin, $otherTerm, 100_000_000);

        try {
            app(CreateProjectDocument::class)->handle($this->admin, $this->project, [
                'document_type_id' => $this->billingDocumentType()->id,
                'client_billing_id' => $otherBilling->id,
                'document_title' => 'Dokumen Penagihan',
            ], $this->fakePdf());
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('Penagihan tidak ditemukan pada project ini.', $exception->getMessage());
        }

        $this->assertSame(0, ProjectDocument::query()->count());
    }

    public function test_billing_can_only_have_one_active_document(): void
    {
        $this->makeBillingDocument($this->admin, $this->billing);

        try {
            app(CreateProjectDocument::class)->handle($this->admin, $this->project, [
                'document_type_id' => $this->billingDocumentType()->id,
                'client_billing_id' => $this->billing->id,
                'document_title' => 'Duplikat',
            ], $this->fakePdf());
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $exception) {
            $this->assertStringContainsString($this->billing->billing_number, $exception->getMessage());
        }

        $this->assertSame(1, ProjectDocument::query()->count());
    }

    public function test_approving_document_does_not_change_billing_status(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));
        $before = $this->billing->fresh();

        app(ApproveProjectDocument::class)->handle($this->admin, $document);

        $after = $this->billing->fresh();
        $this->assertSame(ClientBillingStatus::Sent, $after->status);
        $this->assertSame($before->updated_at->toDateTimeString(), $after->updated_at->toDateTimeString());
        $this->assertSame(0.0, $after->receivedAmount());
        $this->assertSame(300_000_000.0, $after->outstandingAmount());
    }

    public function test_approving_document_does_not_change_client_payments_and_payment_does_not_change_document(): void
    {
        $document = $this->submitAndStartDocumentReview($this->admin, $this->makeBillingDocument($this->admin, $this->billing));
        $payment = $this->recordClientPayment($this->admin, $this->billing, 100_000_000);

        $this->assertSame(ProjectDocumentStatus::UnderReview, $document->fresh()->current_status);

        app(ApproveProjectDocument::class)->handle($this->admin, $document->fresh());

        $payment->refresh();
        $this->assertSame(ClientPaymentStatus::Received, $payment->status);
        $this->assertSame(100_000_000.0, (float) $payment->amount);
        $this->assertSame(1, $this->billing->payments()->count());
        $this->assertSame(200_000_000.0, $this->billing->fresh()->outstandingAmount());
    }

    public function test_cancelled_billing_cannot_get_a_document(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['term_name' => 'Progress', 'percentage' => 20]);
        $draft = $this->makeDraftBilling($this->admin, $term, 50_000_000);
        $draft->forceFill(['status' => ClientBillingStatus::Cancelled])->save();

        try {
            app(CreateProjectDocument::class)->handle($this->admin, $this->project, [
                'document_type_id' => $this->billingDocumentType()->id,
                'client_billing_id' => $draft->id,
                'document_title' => 'Dokumen',
            ], $this->fakePdf());
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('Penagihan yang dibatalkan tidak dapat dibuatkan dokumen.', $exception->getMessage());
        }

        $this->assertSame(0, ProjectDocument::query()->count());
    }
}
