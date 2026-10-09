<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ApproveProjectDocument
{
    public function __construct(private TransitionProjectDocument $transitionProjectDocument) {}

    /**
     * Records the client's approval. Does not touch the billing or its payments.
     *
     * @param  array{approval_notes?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data = []): ProjectDocument
    {
        $validated = Validator::make($data, [
            'approval_notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $notes = $validated['approval_notes'] ?? null;

        return $this->transitionProjectDocument->handle($actor, $document, ProjectDocumentStatus::Approved, $notes, attributes: [
            'approved_by' => $actor->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
            'approved_version' => $document->current_version,
        ]);
    }
}
