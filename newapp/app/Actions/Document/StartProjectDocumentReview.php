<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Models\ProjectDocument;
use App\Models\User;

class StartProjectDocumentReview
{
    public function __construct(private TransitionProjectDocument $transitionProjectDocument) {}

    /**
     * @param  array{notes?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data = []): ProjectDocument
    {
        return $this->transitionProjectDocument->handle($actor, $document, ProjectDocumentStatus::UnderReview, $data['notes'] ?? null);
    }
}
