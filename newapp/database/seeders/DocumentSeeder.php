<?php

namespace Database\Seeders;

use App\Actions\Document\ApproveProjectDocument;
use App\Actions\Document\CreateProjectDocument;
use App\Actions\Document\RequestProjectDocumentRevision;
use App\Actions\Document\ResubmitProjectDocument;
use App\Actions\Document\StartProjectDocumentReview;
use App\Actions\Document\SubmitProjectDocument;
use App\Actions\Document\UploadProjectDocumentVersion;
use App\Models\ClientBilling;
use App\Models\DocumentType;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentStatusHistory;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Demo documents for PRJ-005, built through the real actions so history and versions stay consistent.
 */
class DocumentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'superadmin@example.com')->first()
            ?? User::query()->first();
        $project = Project::query()->where('code', 'PRJ-005')->first();
        $type = DocumentType::query()->where('code', DocumentType::BILLING)->first();

        if ($admin === null || $project === null || $type === null || ! ProjectDocument::projectAcceptsDocuments($project)) {
            return;
        }

        $this->clearProjectDocuments($project);

        $billings = ClientBilling::query()->where('project_id', $project->id)->with('term')->get()->keyBy('billing_number');
        $now = now();

        try {
            if ($billing = $billings->get('BIL-0001')) {
                $this->approvedAfterRevision($admin, $project, $type, $billing, $now);
            }

            if ($billing = $billings->get('BIL-0002')) {
                $this->underReview($admin, $project, $type, $billing, $now);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    private function approvedAfterRevision(User $admin, Project $project, DocumentType $type, ClientBilling $billing, Carbon $now): void
    {
        Carbon::setTestNow($now->copy()->subDays(45));
        $document = app(CreateProjectDocument::class)->handle($admin, $project, [
            'document_type_id' => $type->id,
            'client_billing_id' => $billing->id,
            'document_title' => $type->name.' '.$billing->term?->displayName(),
        ], $this->pdf('Invoice_Termin_1.pdf'));

        Carbon::setTestNow($now->copy()->subDays(44));
        app(SubmitProjectDocument::class)->handle($admin, $document, ['notes' => 'Dikirim via email ke client.']);

        Carbon::setTestNow($now->copy()->subDays(42));
        app(StartProjectDocumentReview::class)->handle($admin, $document->refresh());

        Carbon::setTestNow($now->copy()->subDays(40));
        app(RequestProjectDocumentRevision::class)->handle($admin, $document->refresh(), [
            'revision_reason' => 'Nomor kontrak pada invoice belum dicantumkan.',
        ]);

        Carbon::setTestNow($now->copy()->subDays(39));
        app(UploadProjectDocumentVersion::class)->handle($admin, $document->refresh(), [
            'notes' => 'Menambahkan nomor kontrak pada invoice.',
        ], $this->pdf('Invoice_Termin_1_rev1.pdf'));

        Carbon::setTestNow($now->copy()->subDays(39)->addHours(2));
        app(ResubmitProjectDocument::class)->handle($admin, $document->refresh());

        Carbon::setTestNow($now->copy()->subDays(37));
        app(StartProjectDocumentReview::class)->handle($admin, $document->refresh());

        Carbon::setTestNow($now->copy()->subDays(35));
        app(ApproveProjectDocument::class)->handle($admin, $document->refresh(), [
            'approval_notes' => 'Disetujui client via email.',
        ]);
    }

    private function underReview(User $admin, Project $project, DocumentType $type, ClientBilling $billing, Carbon $now): void
    {
        Carbon::setTestNow($now->copy()->subDays(9));
        $document = app(CreateProjectDocument::class)->handle($admin, $project, [
            'document_type_id' => $type->id,
            'client_billing_id' => $billing->id,
            'document_title' => $type->name.' '.$billing->term?->displayName(),
        ], $this->pdf('Invoice_Termin_2.pdf'));

        Carbon::setTestNow($now->copy()->subDays(8));
        app(SubmitProjectDocument::class)->handle($admin, $document);

        Carbon::setTestNow($now->copy()->subDays(6));
        app(StartProjectDocumentReview::class)->handle($admin, $document->refresh());
    }

    private function clearProjectDocuments(Project $project): void
    {
        $documentIds = ProjectDocument::query()->where('project_id', $project->id)->pluck('id');

        ProjectDocumentVersion::query()->whereIn('project_document_id', $documentIds)->each(
            fn (ProjectDocumentVersion $version) => Storage::disk(ProjectDocumentVersion::DISK)->delete($version->file_path)
        );
        ProjectDocumentVersion::query()->whereIn('project_document_id', $documentIds)->delete();
        ProjectDocumentStatusHistory::query()->whereIn('project_document_id', $documentIds)->delete();
        ProjectDocument::query()->whereKey($documentIds)->delete();
    }

    private function pdf(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
