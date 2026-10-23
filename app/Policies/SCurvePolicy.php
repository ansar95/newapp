<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SCurveBaseline;
use App\Models\User;

class SCurvePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectView);
    }

    public function view(User $user, SCurveBaseline $baseline): bool
    {
        return $user->hasPermission(Permission::ProjectView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::BoqUpdate);
    }

    public function update(User $user, SCurveBaseline $baseline): bool
    {
        return $user->hasPermission(Permission::BoqUpdate);
    }

    public function delete(User $user, SCurveBaseline $baseline): bool
    {
        return false;
    }
}
