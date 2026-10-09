<?php

namespace App\Actions\Document;

use App\Enums\ProjectDocumentStatus;
use App\Models\ProjectDocument;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CancelProjectDocument
{
    public function __construct(private TransitionProjectDocument $transitionProjectDocument) {}

    /**
     * Cancelled documents keep all versions and history.
     *
     * @param  array{cancel_reason: string}  $data
     */
    public function handle(User $actor, ProjectDocument $document, array $data): ProjectDocument
    {
        $validated = Validator::make($data, [
            'cancel_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan minimal 3 karakter.',
        ])->validate();

        return $this->transitionProjectDocument->handle($actor, $document, ProjectDocumentStatus::Cancelled, $validated['cancel_reason'], attributes: [
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['cancel_reason'],
        ]);
    }
}
