<?php

namespace App\Actions\SCurve;

use App\Models\ProgressSnapshot;
use App\Models\Project;

class RecordProgressSnapshot
{
    /**
     * Stores today's physical progress using the existing project formula.
     * A later save on the same day replaces that row.
     */
    public function handle(Project $project): ProgressSnapshot
    {
        $project->unsetRelation('approvedBoqs');
        $percentage = $project->progressPercentage();
        $today = today()->toDateString();

        $snapshot = ProgressSnapshot::query()
            ->where('project_id', $project->id)
            ->whereDate('snapshot_date', $today)
            ->first();

        if ($snapshot !== null) {
            $snapshot->progress_percentage = $percentage;
            $snapshot->save();

            return $snapshot;
        }

        return ProgressSnapshot::query()->create([
            'project_id' => $project->id,
            'snapshot_date' => $today,
            'progress_percentage' => $percentage,
        ]);
    }
}
