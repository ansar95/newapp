<?php

namespace App\Policies;

use App\Enums\BoqStatus;
use App\Enums\Permission;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;

class ProgressPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ProgressView);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProgressView);
    }

    public function update(User $user, BoqItem $item): bool
    {
        if (! $user->hasPermission(Permission::ProgressUpdate)) {
            return false;
        }

        $item->loadMissing('cogs.boq');

        return $item->cogs?->boq?->status === BoqStatus::Approved;
    }
}
