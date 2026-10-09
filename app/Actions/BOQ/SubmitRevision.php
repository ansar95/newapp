<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\User;

class SubmitRevision
{
    public function __construct(
        private PrepareBoqForRevision $prepareBoqForRevision,
        private SubmitBOQ $submitBOQ,
    ) {}

    /**
     * Resubmit a BOQ after revision fixes.
     *
     * Accepts REVISION_REQUIRED (opens to DRAFT first) or DRAFT.
     */
    public function handle(User $actor, Boq $boq): Boq
    {
        $boq->refresh();

        if ($boq->status === BoqStatus::RevisionRequired) {
            $boq = $this->prepareBoqForRevision->handle($actor, $boq);
        }

        if ($boq->status !== BoqStatus::Draft) {
            throw BusinessRuleException::make(
                'BOQ hasil revisi hanya dapat disubmit dari status REVISION_REQUIRED atau DRAFT.'
            );
        }

        return $this->submitBOQ->handle($actor, $boq->fresh(['project', 'cogs.items']));
    }
}
