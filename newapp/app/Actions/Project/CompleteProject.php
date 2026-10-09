<?php

namespace App\Actions\Project;

use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\User;

class CompleteProject
{
    public function handle(User $actor, Project $project): Project
    {
        if ($project->status !== ProjectStatus::Active) {
            throw BusinessRuleException::make('Project hanya dapat diselesaikan dari status ACTIVE.');
        }

        $project->status = ProjectStatus::Completed;
        $project->save();

        return $project->refresh();
    }
}
