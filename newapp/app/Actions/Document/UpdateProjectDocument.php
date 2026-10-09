<?php

namespace App\Actions\Document;

use App\Exceptions\BusinessRuleException;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateProjectDocument
{
    /**
     * @param  array{document_title: string, description?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data): ProjectDocument
    {
        ProjectDocument::assertProjectAcceptsDocuments($document->project);

        if (! $document->current_status->acceptsNewVersion()) {
            throw BusinessRuleException::make('Dokumen berstatus '.$document->current_status->value.' tidak dapat diubah.');
        }

        $validated = Validator::make($data, [
            'document_title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], [
            'document_title.required' => 'Judul dokumen wajib diisi.',
        ])->validate();

        $document->fill([
            'document_title' => $validated['document_title'],
            'description' => $validated['description'] ?? null,
        ])->save();

        return $document->refresh();
    }
}
