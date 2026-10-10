<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ProcurementStatus;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;

class ProcurementRequestPolicy
{
    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProcurementView)
            && $user->hasPermission(Permission::ProjectView);
    }

    public function view(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementView);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::ProcurementCreate)
            && ProcurementRequest::projectAcceptsRequests($project);
    }

    public function update(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementUpdate)
            && $request->isEditable()
            && $this->projectIsActive($request);
    }

    public function submit(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementSubmit)
            && $request->status === ProcurementStatus::Draft
            && $this->projectIsActive($request);
    }

    public function approve(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementApprove)
            && $request->status === ProcurementStatus::Submitted
            && $this->projectIsActive($request);
    }

    public function returnToDraft(User $user, ProcurementRequest $request): bool
    {
        return $this->approve($user, $request);
    }

    public function pay(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementPay)
            && $request->status === ProcurementStatus::Approved
            && $this->projectIsActive($request);
    }

    public function cancel(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementCancel)
            && $request->status->isCancellable()
            && $this->projectIsActive($request);
    }

    public function allocate(User $user, ProcurementRequest $request): bool
    {
        return $user->hasPermission(Permission::ProcurementAllocate)
            && $request->isPaid()
            && $request->type->isAllocatable()
            && $this->projectIsActive($request);
    }

    private function projectIsActive(ProcurementRequest $request): bool
    {
        $request->loadMissing('project');

        return ProcurementRequest::projectAcceptsRequests($request->project);
    }
}
