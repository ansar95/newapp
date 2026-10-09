<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\User;

class PrepareBoqForRevision
{
    /**
     * Move BOQ from REVISION_REQUIRED to DRAFT so items can be fixed.
     */
    public function handle(User $actor, Boq $boq): Boq
    {
        if ($boq->status !== BoqStatus::RevisionRequired) {
            throw BusinessRuleException::make(
                'BOQ hanya dapat dibuka untuk perbaikan ketika statusnya REVISION_REQUIRED.'
            );
        }

        $boq->status = BoqStatus::Draft;
        $boq->save();

        return $boq->refresh();
    }
}
