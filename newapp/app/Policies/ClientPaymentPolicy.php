<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\User;

class ClientPaymentPolicy
{
    public function view(User $user, ClientPayment $payment): bool
    {
        return $user->hasPermission(Permission::BillingPaymentView);
    }

    public function cancel(User $user, ClientPayment $payment): bool
    {
        $payment->loadMissing('billing.project');

        return $user->hasPermission(Permission::BillingPaymentCancel)
            && $payment->isReceived()
            && ClientBilling::projectAcceptsBilling($payment->billing->project);
    }
}
