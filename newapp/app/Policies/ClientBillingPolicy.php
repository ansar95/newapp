<?php

namespace App\Policies;

use App\Enums\ClientBillingStatus;
use App\Enums\Permission;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\User;

class ClientBillingPolicy
{
    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::BillingView)
            && $user->hasPermission(Permission::ProjectView);
    }

    public function view(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingView);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::BillingCreate)
            && ClientBilling::projectAcceptsBilling($project);
    }

    public function update(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingUpdate)
            && $billing->status === ClientBillingStatus::Draft
            && $this->projectAcceptsBilling($billing);
    }

    public function submit(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingSubmit)
            && $billing->status === ClientBillingStatus::Draft
            && $this->projectAcceptsBilling($billing);
    }

    public function returnToDraft(User $user, ClientBilling $billing): bool
    {
        return ($user->hasPermission(Permission::BillingSubmit) || $user->hasPermission(Permission::BillingSend))
            && $billing->status === ClientBillingStatus::Submitted
            && $this->projectAcceptsBilling($billing);
    }

    public function send(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingSend)
            && $billing->status === ClientBillingStatus::Submitted
            && $this->projectAcceptsBilling($billing);
    }

    public function cancel(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingCancel)
            && ! $billing->isCancelled()
            && $billing->receivedAmount() <= 0
            && $this->projectAcceptsBilling($billing);
    }

    public function viewPayments(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingPaymentView);
    }

    public function recordPayment(User $user, ClientBilling $billing): bool
    {
        return $user->hasPermission(Permission::BillingPaymentCreate)
            && $billing->status === ClientBillingStatus::Sent
            && $billing->outstandingAmount() > 0
            && $this->projectAcceptsBilling($billing);
    }

    private function projectAcceptsBilling(ClientBilling $billing): bool
    {
        $billing->loadMissing('project');

        return ClientBilling::projectAcceptsBilling($billing->project);
    }
}
