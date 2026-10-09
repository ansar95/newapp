<?php

namespace App\Actions\Asset;

use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReleaseAssetFromProject
{
    /**
     * @param  array{notes?: string|null}  $data
     */
    public function handle(User $actor, Project $project, ProjectAsset $assignment, array $data = []): ProjectAsset
    {
        $validated = Validator::make($data, [
            'notes' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($actor, $project, $assignment, $validated) {
            /** @var ProjectAsset $lockedAssignment */
            $lockedAssignment = ProjectAsset::query()
                ->whereKey($assignment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Asset $asset */
            $asset = Asset::query()
                ->whereKey($lockedAssignment->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedAssignment->project_id !== (int) $project->id) {
                throw BusinessRuleException::make(
                    'Release hanya dapat dilakukan oleh project yang sedang menggunakan asset.'
                );
            }

            if ($lockedAssignment->status !== ProjectAssetStatus::InUse) {
                throw BusinessRuleException::make(
                    'Assignment asset sudah tidak aktif / sudah di-release.'
                );
            }

            if ($asset->status !== AssetStatus::InUse) {
                throw BusinessRuleException::make(
                    'Asset tidak sedang berstatus IN_USE.'
                );
            }

            $lockedAssignment->status = ProjectAssetStatus::Released;
            $lockedAssignment->released_at = now();
            $lockedAssignment->released_by = $actor->id;

            if (array_key_exists('notes', $validated) && $validated['notes'] !== null) {
                $lockedAssignment->notes = $validated['notes'];
            }

            $lockedAssignment->save();

            $asset->status = AssetStatus::Available;
            $asset->save();

            return $lockedAssignment->fresh(['asset', 'project']);
        });
    }
}
