<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitBOQ
{
    public function handle(User $actor, Boq $boq): Boq
    {
        $boq->loadMissing('project', 'cogs.items');

        if (! in_array($boq->status, [BoqStatus::Draft], true)) {
            throw BusinessRuleException::make(
                'BOQ hanya dapat disubmit dari status DRAFT.'
            );
        }

        $this->assertReadyForSubmission($boq);

        return DB::transaction(function () use ($actor, $boq): Boq {
            $boq->status = BoqStatus::WaitingApproval;
            $boq->submitted_by = $actor->id;
            $boq->submitted_at = now();
            $boq->save();

            return $boq->refresh();
        });
    }

    private function assertReadyForSubmission(Boq $boq): void
    {
        if ($boq->project === null) {
            throw BusinessRuleException::make('BOQ tidak dapat disubmit karena belum memiliki Project.');
        }

        if ($boq->cogs->isEmpty()) {
            throw BusinessRuleException::make('BOQ tidak dapat disubmit karena belum memiliki COGS.');
        }

        foreach ($boq->cogs as $cogs) {
            if ($cogs->items->isEmpty()) {
                throw BusinessRuleException::make(
                    'BOQ tidak dapat disubmit karena COGS '.$cogs->code.' belum memiliki item.'
                );
            }

            foreach ($cogs->items as $item) {
                $this->assertValidItem($item);
            }
        }

        if ($boq->totalAmount() <= 0) {
            throw BusinessRuleException::make('BOQ tidak dapat disubmit karena total BOQ harus lebih dari 0.');
        }
    }

    private function assertValidItem(BoqItem $item): void
    {
        if ((float) $item->quantity < 0) {
            throw BusinessRuleException::make(
                'BOQ tidak dapat disubmit karena quantity pada item '.$item->code.' tidak valid.'
            );
        }

        if ((float) $item->unit_price < 0) {
            throw BusinessRuleException::make(
                'BOQ tidak dapat disubmit karena unit price pada item '.$item->code.' tidak valid.'
            );
        }
    }
}
