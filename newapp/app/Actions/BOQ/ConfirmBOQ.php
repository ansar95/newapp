<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Enums\RevisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmBOQ
{
    public function handle(User $actor, Boq $boq, ?string $reviewNotes = null): Boq
    {
        if ($boq->status !== BoqStatus::WaitingApproval) {
            throw BusinessRuleException::make(
                'BOQ tidak dapat dikonfirmasi karena status BOQ bukan WAITING_APPROVAL.'
            );
        }

        return DB::transaction(function () use ($actor, $boq, $reviewNotes): Boq {
            $boq->status = BoqStatus::Approved;
            $boq->reviewed_by = $actor->id;
            $boq->reviewed_at = now();
            $boq->review_notes = $reviewNotes;
            $boq->save();

            $boq->revisions()
                ->where('status', RevisionStatus::Open)
                ->update([
                    'status' => RevisionStatus::Resolved,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);

            return $boq->refresh();
        });
    }
}
