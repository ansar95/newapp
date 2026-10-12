<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\ProjectManPower;
use App\Models\User;

class ProjectManPowerPolicy
{
    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ManPowerView)
            && $user->hasPermission(Permission::ProjectView);
    }

    public function assign(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ManPowerManage)
            && $project->acceptsManPowerAssignment();
    }

    public function update(User $user, ProjectManPower $projectManPower): bool
    {
        $projectManPower->loadMissing('project');

        return $user->hasPermission(Permission::ManPowerManage)
            && $projectManPower->project->acceptsManPowerAssignment();
    }

    public function recordTransaction(User $user, ProjectManPower $projectManPower): bool
    {
        $projectManPower->loadMissing('project');

        return $user->hasPermission(Permission::ManPowerPay)
            && $projectManPower->project->acceptsManPowerPayment();
    }
}
