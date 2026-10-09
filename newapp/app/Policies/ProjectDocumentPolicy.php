<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\ProjectDocumentStatus;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\User;

class ProjectDocumentPolicy
{
    public function viewForProject(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::DocumentView);
    }

    public function view(User $user, ProjectDocument $document): bool
    {
        return $user->hasPermission(Permission::DocumentView);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->hasPermission(Permission::DocumentCreate)
            && ProjectDocument::projectAcceptsDocuments($project);
    }

    public function update(User $user, ProjectDocument $document): bool
    {
        return $user->hasPermission(Permission::DocumentUpdate)
            && $document->current_status->acceptsNewVersion()
            && $this->projectAccepts($document);
    }

    /**
     * Draft files are replaced with document.update; revision files need document.resubmit.
     */
    public function uploadVersion(User $user, ProjectDocument $document): bool
    {
        $permission = $document->current_status === ProjectDocumentStatus::RevisionRequired
            ? Permission::DocumentResubmit
            : Permission::DocumentUpdate;

        return $user->hasPermission($permission)
            && $document->current_status->acceptsNewVersion()
            && $this->projectAccepts($document);
    }

    public function submit(User $user, ProjectDocument $document): bool
    {
        return $this->canMove($user, $document, Permission::DocumentSubmit, ProjectDocumentStatus::Submitted)
            && $document->current_version > 0;
    }

    /**
     * Client review is recorded internally, so it shares the approve permission.
     */
    public function startReview(User $user, ProjectDocument $document): bool
    {
        return $this->canMove($user, $document, Permission::DocumentApprove, ProjectDocumentStatus::UnderReview);
    }

    public function approve(User $user, ProjectDocument $document): bool
    {
        return $this->canMove($user, $document, Permission::DocumentApprove, ProjectDocumentStatus::Approved);
    }

    public function requestRevision(User $user, ProjectDocument $document): bool
    {
        return $this->canMove($user, $document, Permission::DocumentRevision, ProjectDocumentStatus::RevisionRequired);
    }

    public function resubmit(User $user, ProjectDocument $document): bool
    {
        return $this->canMove($user, $document, Permission::DocumentResubmit, ProjectDocumentStatus::Resubmitted)
            && $document->hasVersionAfterRevisionRequest();
    }

    public function cancel(User $user, ProjectDocument $document): bool
    {
        return $this->canMove($user, $document, Permission::DocumentCancel, ProjectDocumentStatus::Cancelled);
    }

    public function download(User $user, ProjectDocument $document): bool
    {
        return $user->hasPermission(Permission::DocumentView)
            && $user->hasPermission(Permission::DocumentDownload);
    }

    private function canMove(User $user, ProjectDocument $document, Permission $permission, ProjectDocumentStatus $target): bool
    {
        return $user->hasPermission($permission)
            && $document->current_status->canTransitionTo($target)
            && $this->projectAccepts($document);
    }

    private function projectAccepts(ProjectDocument $document): bool
    {
        $document->loadMissing('project');

        return ProjectDocument::projectAcceptsDocuments($document->project);
    }
}
