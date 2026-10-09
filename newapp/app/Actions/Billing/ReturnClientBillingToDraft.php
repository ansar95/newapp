<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\User;

class ReturnClientBillingToDraft
{
    public function handle(User $actor, ClientBilling $billing): ClientBilling
    {
        ClientBilling::assertProjectAcceptsBilling($billing->project);

        if ($billing->status !== ClientBillingStatus::Submitted) {
            throw BusinessRuleException::make('Hanya penagihan berstatus SUBMITTED yang dapat dikembalikan ke DRAFT.');
        }

        $billing->forceFill([
            'status' => ClientBillingStatus::Draft,
            'submitted_by' => null,
            'submitted_at' => null,
        ])->save();

        return $billing->refresh();
    }
}
