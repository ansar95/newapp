<?php

namespace App\Policies;

use App\Enums\BmhpPurchaseStatus;
use App\Enums\Permission;
use App\Models\BmhpPurchase;
use App\Models\Project;
use App\Models\User;

class BmhpPurchasePolicy
{
    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::BmhpView)
            && $user->hasPermission(Permission::ProjectView);
    }

    public function view(User $user, BmhpPurchase $purchase): bool
    {
        return $user->hasPermission(Permission::BmhpView);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::BmhpCreate)
            && BmhpPurchase::projectAcceptsPurchases($project);
    }

    public function update(User $user, BmhpPurchase $purchase): bool
    {
        return $user->hasPermission(Permission::BmhpUpdate)
            && $purchase->isEditable()
            && $this->projectIsActive($purchase);
    }

    public function allocate(User $user, BmhpPurchase $purchase): bool
    {
        return $user->hasPermission(Permission::BmhpAllocate)
            && $purchase->isEditable()
            && $this->projectIsActive($purchase);
    }

    public function submit(User $user, BmhpPurchase $purchase): bool
    {
        return $user->hasPermission(Permission::BmhpSubmit)
            && $purchase->status === BmhpPurchaseStatus::Draft
            && $this->projectIsActive($purchase);
    }

    public function confirm(User $user, BmhpPurchase $purchase): bool
    {
        return $user->hasPermission(Permission::BmhpConfirm)
            && $purchase->status === BmhpPurchaseStatus::Submitted
            && $this->projectIsActive($purchase);
    }

    public function returnToDraft(User $user, BmhpPurchase $purchase): bool
    {
        return $this->confirm($user, $purchase);
    }

    public function cancel(User $user, BmhpPurchase $purchase): bool
    {
        return $user->hasPermission(Permission::BmhpCancel)
            && $purchase->status !== BmhpPurchaseStatus::Cancelled
            && $this->projectIsActive($purchase);
    }

    private function projectIsActive(BmhpPurchase $purchase): bool
    {
        $purchase->loadMissing('project');

        return BmhpPurchase::projectAcceptsPurchases($purchase->project);
    }
}
