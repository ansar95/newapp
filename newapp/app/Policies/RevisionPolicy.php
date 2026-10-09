<?php

namespace App\Policies;

use App\Enums\BoqStatus;
use App\Enums\Permission;
use App\Enums\RevisionStatus;
use App\Models\Boq;
use App\Models\BoqRevision;
use App\Models\User;

class RevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::RevisionView);
    }

    public function view(User $user, BoqRevision $revision): bool
    {
        return $user->hasPermission(Permission::RevisionView);
    }

    public function viewForBoq(User $user, Boq $boq): bool
    {
        return $user->hasPermission(Permission::RevisionView);
    }

    public function create(User $user, Boq $boq): bool
    {
        if (! $this->canOpenRevision($user, $boq)) {
            return false;
        }

        return in_array($boq->status, [BoqStatus::WaitingApproval, BoqStatus::Approved], true);
    }

    private function canOpenRevision(User $user, Boq $boq): bool
    {
        if ($user->hasPermission(Permission::RevisionCreate)) {
            return true;
        }

        return $boq->status === BoqStatus::Approved
            && $user->hasPermission(Permission::BoqUpdate)
            && $user->hasPermission(Permission::RevisionUpdate);
    }

    public function prepare(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::RevisionUpdate)) {
            return false;
        }

        return $boq->status === BoqStatus::RevisionRequired;
    }

    public function update(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::RevisionUpdate)) {
            return false;
        }

        return in_array($boq->status, [BoqStatus::Draft, BoqStatus::RevisionRequired], true);
    }

    public function submit(User $user, Boq $boq): bool
    {
        if (! $user->hasPermission(Permission::RevisionSubmit)) {
            return false;
        }

        return in_array($boq->status, [BoqStatus::Draft, BoqStatus::RevisionRequired], true)
            && $boq->revisions()->where('status', RevisionStatus::Open)->exists();
    }
}
