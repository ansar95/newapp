<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectView);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ProjectCreate);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectUpdate);
    }

    public function activate(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectUpdate);
    }

    public function complete(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectUpdate);
    }

    public function viewReports(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProjectView)
            && $user->hasPermission(Permission::ReportView);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isSuperadmin();
    }

    public function export(User $user, Project $project): bool
    {
        return $user->isSuperadmin();
    }

    public function import(User $user): bool
    {
        return $user->isSuperadmin();
    }
}
