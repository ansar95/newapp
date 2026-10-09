<?php

namespace App\Policies;

use App\Enums\BoqStatus;
use App\Enums\Permission;
use App\Models\Boq;
use App\Models\User;

class BoqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::BoqView);
    }

    public function view(User $user, Boq $boq): bool
    {
        return $user->hasPermission(Permission::BoqView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::BoqCreate);
    }

    public function update(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::BoqUpdate)) {
            return false;
        }

        return $boq->status === BoqStatus::Draft;
    }

    public function submit(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::BoqSubmit)) {
            return false;
        }

        return $boq->status === BoqStatus::Draft;
    }

    public function review(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::BoqReview)) {
            return false;
        }

        return $boq->status === BoqStatus::WaitingApproval;
    }

    public function approve(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::BoqApprove)) {
            return false;
        }

        return $boq->status === BoqStatus::WaitingApproval;
    }

    public function requestRevision(User $user, Boq $boq): bool
    {
        if ($boq->status === BoqStatus::WaitingApproval) {
            return $user->hasPermission(Permission::BoqRequestRevision);
        }

        if ($boq->status === BoqStatus::Approved) {
            if ($user->hasPermission(Permission::BoqRequestRevision)) {
                return true;
            }

            return $user->hasPermission(Permission::BoqUpdate)
                && $user->hasPermission(Permission::RevisionUpdate);
        }

        return false;
    }
}
