<?php

namespace App\Actions\Document;

use App\Enums\DocumentActorType;
use App\Enums\ProjectDocumentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place a document status changes. Validates the transition and records it in the history.
 */
class TransitionProjectDocument
{
    /**
     * @param  array<string, mixed>  $attributes  extra document columns to update with the status
     */
    public function handle(
        User $actor,
        ProjectDocument $document,
        ProjectDocumentStatus $target,
        ?string $notes = null,
        ?string $revisionReason = null,
        array $attributes = [],
    ): ProjectDocument {
        ProjectDocument::assertProjectAcceptsDocuments($document->project);

        return DB::transaction(function () use ($actor, $document, $target, $notes, $revisionReason, $attributes): ProjectDocument {
            $locked = ProjectDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $from = $locked->current_status;

            if (! $from->canTransitionTo($target)) {
                throw BusinessRuleException::make(
                    'Status dokumen tidak dapat diubah dari '.$from->value.' ke '.$target->value.'.'
                );
            }

            $locked->forceFill([...$attributes, 'current_status' => $target])->save();

            ProjectDocumentStatusHistory::query()->create([
                'project_document_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $target,
                'version_number' => $locked->current_version,
                'notes' => $notes,
                'revision_reason' => $revisionReason,
                'actor_type' => DocumentActorType::Internal,
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            return $locked->refresh();
        });
    }
}
