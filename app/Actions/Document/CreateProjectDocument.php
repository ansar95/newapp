<?php

namespace App\Actions\Document;

use App\Enums\ClientBillingStatus;
use App\Enums\DocumentActorType;
use App\Enums\DocumentReferenceType;
use App\Enums\ProjectDocumentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\DocumentType;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentStatusHistory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateProjectDocument
{
    public function __construct(private StoreProjectDocumentVersion $storeProjectDocumentVersion) {}

    /**
     * Create a document in DRAFT with its first version (V1).
     *
     * @param  array{
     *     document_type_id: int|string,
     *     client_billing_id?: int|string|null,
     *     document_title: string,
     *     description?: string|null,
     *     version_notes?: string|null
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data, ?UploadedFile $file): ProjectDocument
    {
        ProjectDocument::assertProjectAcceptsDocuments($project);

        $validated = Validator::make([...$data, 'file' => $file], [
            'document_type_id' => ['required', 'integer'],
            'client_billing_id' => ['nullable', 'integer'],
            'document_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'version_notes' => ['nullable', 'string', 'max:2000'],
            'file' => StoreProjectDocumentVersion::fileRules(),
        ], [
            'document_type_id.required' => 'Tipe dokumen wajib dipilih.',
            'document_title.required' => 'Judul dokumen wajib diisi.',
            ...StoreProjectDocumentVersion::fileMessages(),
        ])->validate();

        $type = DocumentType::query()->whereKey($validated['document_type_id'])->where('is_active', true)->first();

        if ($type === null) {
            throw BusinessRuleException::make('Tipe dokumen tidak tersedia.');
        }

        return DB::transaction(function () use ($actor, $project, $validated, $type, $file): ProjectDocument {
            $billing = $type->requiresBilling()
                ? $this->resolveBilling($project, $type, $validated['client_billing_id'] ?? null)
                : null;

            $document = ProjectDocument::query()->create([
                'project_id' => $project->id,
                'document_type_id' => $type->id,
                'reference_type' => $billing ? DocumentReferenceType::Billing->value : null,
                'reference_id' => $billing?->id,
                'document_number' => ProjectDocument::nextDocumentNumber(),
                'document_title' => $validated['document_title'],
                'description' => $validated['description'] ?? null,
                'current_status' => ProjectDocumentStatus::Draft,
                'current_version' => 0,
                'created_by' => $actor->id,
            ]);

            ProjectDocumentStatusHistory::query()->create([
                'project_document_id' => $document->id,
                'from_status' => null,
                'to_status' => ProjectDocumentStatus::Draft,
                'version_number' => 1,
                'notes' => 'Dokumen dibuat.',
                'actor_type' => DocumentActorType::Internal,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            $this->storeProjectDocumentVersion->handle($actor, $document, $file, $validated['version_notes'] ?? null);

            return $document->refresh();
        });
    }

    private function resolveBilling(Project $project, DocumentType $type, int|string|null $billingId): ClientBilling
    {
        if ($billingId === null || $billingId === '') {
            throw BusinessRuleException::make('Penagihan wajib dipilih untuk '.$type->name.'.');
        }

        $billing = ClientBilling::query()->whereKey($billingId)->lockForUpdate()->first();

        if ($billing === null || (int) $billing->project_id !== (int) $project->id) {
            throw BusinessRuleException::make('Penagihan tidak ditemukan pada project ini.');
        }

        if ($billing->status === ClientBillingStatus::Cancelled) {
            throw BusinessRuleException::make('Penagihan yang dibatalkan tidak dapat dibuatkan dokumen.');
        }

        $existing = ProjectDocument::query()
            ->where('reference_type', DocumentReferenceType::Billing->value)
            ->where('reference_id', $billing->id)
            ->where('document_type_id', $type->id)
            ->where('current_status', '!=', ProjectDocumentStatus::Cancelled)
            ->first();

        if ($existing !== null) {
            throw BusinessRuleException::make(
                'Penagihan '.$billing->billing_number.' sudah memiliki '.$type->name.' aktif ('.$existing->document_number.').'
            );
        }

        return $billing;
    }
}
