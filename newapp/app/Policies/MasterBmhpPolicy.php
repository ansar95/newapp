<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MasterBmhp;
use App\Models\User;

class MasterBmhpPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::BmhpView)
            || $user->hasPermission(Permission::BmhpManageMaster);
    }

    public function view(User $user, MasterBmhp $masterBmhp): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::BmhpManageMaster);
    }

    /**
     * Import both creates and updates masters.
     */
    public function import(User $user): bool
    {
        return $user->hasPermission(Permission::BmhpManageMaster);
    }

    public function update(User $user, MasterBmhp $masterBmhp): bool
    {
        return $user->hasPermission(Permission::BmhpManageMaster);
    }

    public function delete(User $user, MasterBmhp $masterBmhp): bool
    {
        return $user->hasPermission(Permission::BmhpManageMaster);
    }
}
