<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;

class ProjectTermPolicy
{
    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::BillingTermManage)
            && ClientBilling::projectAcceptsBilling($project);
    }

    public function update(User $user, ProjectTerm $term): bool
    {
        $term->loadMissing('project');

        return $user->hasPermission(Permission::BillingTermManage)
            && ! $term->isCancelled()
            && ClientBilling::projectAcceptsBilling($term->project);
    }

    public function delete(User $user, ProjectTerm $term): bool
    {
        return $this->update($user, $term);
    }
}
