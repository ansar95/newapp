<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class RequestProjectDocumentRevision
{
    public function __construct(private TransitionProjectDocument $transitionProjectDocument) {}

    /**
     * @param  array{revision_reason: string}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data): ProjectDocument
    {
        $validated = Validator::make($data, [
            'revision_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'revision_reason.required' => 'Alasan revisi wajib diisi.',
            'revision_reason.min' => 'Alasan revisi minimal 3 karakter.',
        ])->validate();

        return $this->transitionProjectDocument->handle(
            $actor,
            $document,
            ProjectDocumentStatus::RevisionRequired,
            revisionReason: $validated['revision_reason'],
        );
    }
}
