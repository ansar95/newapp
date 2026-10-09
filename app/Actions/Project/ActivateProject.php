<?php

namespace App\Actions\Project;

use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\User;

class ActivateProject
{
    public function handle(User $actor, Project $project): Project
    {
        if ($project->status !== ProjectStatus::Draft) {
            throw BusinessRuleException::make('Project hanya dapat diaktifkan dari status DRAFT.');
        }

        if (! $this->hasCompleteBasicData($project)) {
            throw BusinessRuleException::make(
                'Project tidak boleh ACTIVE jika data dasar project belum lengkap (code, name, dan client name).'
            );
        }

        $project->status = ProjectStatus::Active;
        $project->save();

        return $project->refresh();
    }

    private function hasCompleteBasicData(Project $project): bool
    {
        return filled($project->code)
            && filled($project->name)
            && filled($project->client_name);
    }
}
