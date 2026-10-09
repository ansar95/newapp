<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Enums\RevisionItemStatus;
use App\Enums\RevisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RequestRevision
{
    /**
     * @param  array{
     *     reason: string,
     *     item_ids: list<int>,
     *     item_reasons?: array<int, string>
     * }  $data
     */
    public function handle(User $actor, Boq $boq, array $data): BoqRevision
    {
        if (! in_array($boq->status, [BoqStatus::WaitingApproval, BoqStatus::Approved], true)) {
            throw BusinessRuleException::make(
                'BOQ hanya dapat direvisi ketika statusnya WAITING_APPROVAL atau APPROVED.'
            );
        }

        if ($boq->revisions()->where('status', RevisionStatus::Open)->exists()) {
            throw BusinessRuleException::make(
                'BOQ masih memiliki revision yang belum selesai.'
            );
        }

        $validated = Validator::make($data, [
            'reason' => ['required', 'string', 'min:3'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'integer', 'distinct'],
            'item_reasons' => ['nullable', 'array'],
            'item_reasons.*' => ['nullable', 'string'],
        ], [
            'reason.required' => 'Revision wajib memiliki alasan.',
            'reason.min' => 'Revision wajib memiliki alasan.',
            'item_ids.required' => 'Revision wajib memiliki minimal satu item.',
            'item_ids.min' => 'Revision wajib memiliki minimal satu item.',
        ])->validate();

        $boq->loadMissing('cogs.items');

        $validItemIds = $boq->cogs
            ->flatMap(fn ($cogs) => $cogs->items->pluck('id'))
            ->all();

        foreach ($validated['item_ids'] as $itemId) {
            if (! in_array($itemId, $validItemIds, true)) {
                throw BusinessRuleException::make(
                    'Item yang dipilih untuk revisi tidak termasuk dalam BOQ ini.'
                );
            }
        }

        return DB::transaction(function () use ($actor, $boq, $validated): BoqRevision {
            $revisionNumber = ((int) $boq->revisions()->max('revision_number')) + 1;
            $revisionCode = sprintf('%s-REV%02d', $boq->code, $revisionNumber);

            if ($boq->revisions()->where('revision_code', $revisionCode)->exists()) {
                throw BusinessRuleException::make('Revision code sudah digunakan pada BOQ ini.');
            }

            $revision = $boq->revisions()->create([
                'revision_code' => $revisionCode,
                'revision_number' => $revisionNumber,
                'reason' => $validated['reason'],
                'status' => RevisionStatus::Open,
                'created_by' => $actor->id,
            ]);

            foreach ($validated['item_ids'] as $itemId) {
                $itemReason = $validated['item_reasons'][$itemId]
                    ?? $validated['reason'];

                $revision->revisionItems()->create([
                    'boq_item_id' => $itemId,
                    'reason' => $itemReason,
                    'status' => RevisionItemStatus::Pending,
                ]);
            }

            $boq->status = BoqStatus::RevisionRequired;
            $boq->reviewed_by = $actor->id;
            $boq->reviewed_at = now();
            $boq->review_notes = $validated['reason'];
            $boq->save();

            return $revision->fresh(['revisionItems']);
        });
    }
}
