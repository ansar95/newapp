<?php

namespace App\Actions\Progress;

use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\InvalidProgressPercentageException;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateItemProgress
{
    public function handle(User $actor, BoqItem $item, float|int|string $progressPercentage): BoqItem
    {
        $item->loadMissing('cogs.boq.paymentPlans');

        $boq = $item->cogs?->boq;

        if ($boq === null) {
            throw BusinessRuleException::make('Item tidak terhubung ke BOQ yang valid.');
        }

        if ($boq->status !== BoqStatus::Approved) {
            throw BusinessRuleException::make(
                'Progress item hanya dapat diubah setelah BOQ berstatus APPROVED.'
            );
        }

        if (! $this->boqHasPaidPayment($boq)) {
            throw BusinessRuleException::make(
                'Progress item hanya dapat diubah setelah Payment terkait berstatus PAID.'
            );
        }

        $validated = Validator::make(
            ['progress_percentage' => $progressPercentage],
            ['progress_percentage' => ['required', 'numeric', 'min:0', 'max:100']],
            [
                'progress_percentage.min' => 'Progress harus berada antara 0 dan 100.',
                'progress_percentage.max' => 'Progress harus berada antara 0 dan 100.',
            ]
        )->validate();

        try {
            $item->progress_percentage = $validated['progress_percentage'];
            $item->save();
        } catch (InvalidProgressPercentageException) {
            throw BusinessRuleException::make('Progress harus berada antara 0 dan 100.');
        }

        $this->syncProjectCompletion($boq);

        return $item->refresh();
    }

    private function syncProjectCompletion(Boq $boq): void
    {
        $project = $boq->project()->with('approvedBoqs.cogs.items')->first();

        if ($project === null) {
            return;
        }

        if ($project->status === ProjectStatus::Active && $project->progressPercentage() >= 100) {
            $project->status = ProjectStatus::Completed;
            $project->save();
        }
    }

    private function boqHasPaidPayment(Boq $boq): bool
    {
        return $boq->paymentPlans
            ->contains(fn ($plan): bool => $plan->status === PaymentPlanStatus::Paid);
    }
}
