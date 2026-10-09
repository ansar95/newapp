<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\User;

class ProjectAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::AssetView);
    }

    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::AssetView)
            && $user->hasPermission(Permission::ProjectView);
    }

    public function view(User $user, ProjectAsset $projectAsset): bool
    {
        return $user->hasPermission(Permission::AssetView);
    }

    public function assign(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::AssetAssign)
            && $user->hasPermission(Permission::ProjectView);
    }

    public function release(User $user, ProjectAsset $projectAsset): bool
    {
        return $user->hasPermission(Permission::AssetRelease);
    }
}
