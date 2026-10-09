<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProjectDocument;
use App\Models\User;

class ResubmitProjectDocument
{
    public function __construct(private TransitionProjectDocument $transitionProjectDocument) {}

    /**
     * @param  array{notes?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data = []): ProjectDocument
    {
        $document->load('histories');

        if ($document->current_status === ProjectDocumentStatus::RevisionRequired && ! $document->hasVersionAfterRevisionRequest()) {
            throw BusinessRuleException::make('Upload versi revisi terlebih dahulu sebelum resubmit.');
        }

        return $this->transitionProjectDocument->handle($actor, $document, ProjectDocumentStatus::Resubmitted, $data['notes'] ?? null);
    }
}
