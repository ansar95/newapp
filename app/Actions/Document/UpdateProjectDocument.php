<?php

namespace App\Actions\Document;

use App\Exceptions\BusinessRuleException;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateProjectDocument
{
    /**
     * @param  array{document_title: string, type_label?: string|null, description?: string|null}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data): ProjectDocument
    {
        ProjectDocument::assertProjectAcceptsDocuments($document->project);

        if (! $document->current_status->acceptsNewVersion()) {
            throw BusinessRuleException::make('Dokumen berstatus '.$document->current_status->value.' tidak dapat diubah.');
        }

        if (isset($data['type_label']) && is_string($data['type_label'])) {
            $data['type_label'] = trim($data['type_label']);
        }

        $validated = Validator::make($data, [
            'document_title' => ['required', 'string', 'max:255'],
            'type_label' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ], [
            'document_title.required' => 'Judul dokumen wajib diisi.',
            'type_label.required' => 'Tipe dokumen wajib diisi.',
        ])->validate();

        $attributes = [
            'document_title' => $validated['document_title'],
            'description' => $validated['description'] ?? null,
        ];

        if (array_key_exists('type_label', $validated)) {
            $attributes['type_label'] = $validated['type_label'];
        }

        $document->fill($attributes)->save();

        return $document->refresh();
    }
}
