<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProjectDocument;
use App\Models\User;

class SubmitProjectDocument
{
    public function __construct(private TransitionProjectDocument $transitionProjectDocument) {}

    /**
     * @param  array{notes?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data = []): ProjectDocument
    {
        if ($document->current_version < 1) {
            throw BusinessRuleException::make('Dokumen harus memiliki minimal satu versi sebelum disubmit.');
        }

        return $this->transitionProjectDocument->handle($actor, $document, ProjectDocumentStatus::Submitted, $data['notes'] ?? null);
    }
}
