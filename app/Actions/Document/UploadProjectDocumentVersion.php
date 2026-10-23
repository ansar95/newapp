<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UploadProjectDocumentVersion
{
    public function __construct(private StoreProjectDocumentVersion $storeProjectDocumentVersion) {}

    /**
     * Upload a new version. In REVISION_REQUIRED the status stays the same until the document is resubmitted.
     *
     * @param  array{notes?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data, ?UploadedFile $file): ProjectDocumentVersion
    {
        ProjectDocument::assertProjectAcceptsDocuments($document->project);

        if (! $document->current_status->acceptsNewVersion()) {
            throw BusinessRuleException::make('Berkas baru hanya dapat ditambahkan pada dokumen arsip.');
        }

        $isRevision = $document->current_status === ProjectDocumentStatus::RevisionRequired;

        $validated = Validator::make([...$data, 'file' => $file], [
            'notes' => [$isRevision ? 'required' : 'nullable', 'string', 'max:2000'],
            'file' => StoreProjectDocumentVersion::fileRules(),
        ], [
            'notes.required' => 'Catatan revisi wajib diisi.',
            ...StoreProjectDocumentVersion::fileMessages(),
        ])->validate();

        return DB::transaction(function () use ($actor, $document, $validated, $file): ProjectDocumentVersion {
            $locked = ProjectDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! $locked->current_status->acceptsNewVersion()) {
                throw BusinessRuleException::make('Berkas baru hanya dapat ditambahkan pada dokumen arsip.');
            }

            return $this->storeProjectDocumentVersion->handle($actor, $locked, $file, $validated['notes'] ?? null);
        });
    }
}
