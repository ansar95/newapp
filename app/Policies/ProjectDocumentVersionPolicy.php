<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProjectDocumentVersion;
use App\Models\User;

class ProjectDocumentVersionPolicy
{
    public function download(User $user, ProjectDocumentVersion $version): bool
    {
        return $user->hasPermission(Permission::DocumentView)
            && $user->hasPermission(Permission::DocumentDownload);
    }
}
