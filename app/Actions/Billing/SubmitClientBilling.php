<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\User;

class SubmitClientBilling
{
    public function handle(User $actor, ClientBilling $billing): ClientBilling
    {
        ClientBilling::assertProjectAcceptsBilling($billing->project);

        if ($billing->status !== ClientBillingStatus::Draft) {
            throw BusinessRuleException::make('Hanya penagihan berstatus DRAFT yang dapat di-submit.');
        }

        $billing->forceFill([
            'status' => ClientBillingStatus::Submitted,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
        ])->save();

        return $billing->refresh();
    }
}
