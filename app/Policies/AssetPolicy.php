<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::AssetView)
            || $user->hasPermission(Permission::AssetManageMaster);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::AssetView)
            || $user->hasPermission(Permission::AssetManageMaster);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::AssetManageMaster)
            || $user->hasPermission(Permission::AssetCreate);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::AssetManageMaster)
            || $user->hasPermission(Permission::AssetUpdate);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::AssetManageMaster);
    }

    public function manageRequest(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::AssetManageRequest)
            || $user->hasPermission(Permission::AssetManageMaster);
    }

    public function markAvailable(User $user, Asset $asset): bool
    {
        return $this->manageRequest($user, $asset)
            || $this->update($user, $asset);
    }
}
