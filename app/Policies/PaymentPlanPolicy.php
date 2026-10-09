<?php

namespace App\Policies;

use App\Enums\PaymentPlanStatus;
use App\Enums\Permission;
use App\Models\PaymentPlan;
use App\Models\Project;
use App\Models\User;

class PaymentPlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PaymentPlanView);
    }

    public function view(User $user, PaymentPlan $paymentPlan): bool
    {
        return $user->hasPermission(Permission::PaymentPlanView);
    }

    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::PaymentPlanView);
    }

    public function create(User $user, Project $project): bool
    {
        if (! $user->hasPermission(Permission::PaymentPlanCreate)) {
            return false;
        }

        return $project->approvedBoqs()->exists();
    }

    public function update(User $user, PaymentPlan $paymentPlan): bool
    {
        if (! $user->hasPermission(Permission::PaymentPlanUpdate)) {
            return false;
        }

        return in_array($paymentPlan->status, [
            PaymentPlanStatus::Planned,
            PaymentPlanStatus::ReadyToPay,
        ], true);
    }

    public function markReady(User $user, PaymentPlan $paymentPlan): bool
    {
        return $this->update($user, $paymentPlan)
            && $paymentPlan->status === PaymentPlanStatus::Planned;
    }
}
