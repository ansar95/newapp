<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ManPower;
use App\Models\User;

class ManPowerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManPowerView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManPowerManage);
    }

    public function update(User $user, ManPower $manPower): bool
    {
        return $user->hasPermission(Permission::ManPowerManage);
    }
}
