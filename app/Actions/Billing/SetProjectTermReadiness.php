<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Enums\ProjectTermReadiness;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\ProjectTerm;
use App\Models\User;

class SetProjectTermReadiness
{
    public function handle(User $actor, ProjectTerm $term, bool $ready): ProjectTerm
    {
        ClientBilling::assertProjectAcceptsBilling($term->project);

        if ($term->isCancelled()) {
            throw BusinessRuleException::make('Termin yang sudah dibatalkan tidak dapat diubah.');
        }

        if (! $ready && $term->billings()->where('status', '!=', ClientBillingStatus::Cancelled)->exists()) {
            throw BusinessRuleException::make('Termin yang sudah memiliki penagihan tidak dapat dikembalikan ke NOT READY.');
        }

        $term->readiness = $ready ? ProjectTermReadiness::Ready : ProjectTermReadiness::NotReady;
        $term->save();

        return $term->refresh();
    }
}
