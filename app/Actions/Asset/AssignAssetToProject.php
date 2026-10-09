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

class AssignAssetToProject
{
    public function __construct(
        private CanAssignAsset $canAssignAsset,
    ) {}

    /**
     * @param  array{asset_id: int|string, notes?: string|null}  $data
     */
    public function handle(User $actor, Project $project, array $data): ProjectAsset
    {
        $validated = Validator::make($data, [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'notes' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($actor, $project, $validated) {
            /** @var Asset $asset */
            $asset = Asset::query()
                ->whereKey($validated['asset_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $check = $this->canAssignAsset->evaluate($asset, $project);

            if (! $check['allowed']) {
                throw BusinessRuleException::make(
                    $check['message'] ?? 'Asset tidak dapat digunakan pada project ini.'
                );
            }

            // Re-check under lock for race conditions.
            $alreadyActive = ProjectAsset::query()
                ->where('asset_id', $asset->id)
                ->where('status', ProjectAssetStatus::InUse)
                ->lockForUpdate()
                ->exists();

            if ($alreadyActive || $asset->status !== AssetStatus::Available) {
                $active = ProjectAsset::query()
                    ->where('asset_id', $asset->id)
                    ->where('status', ProjectAssetStatus::InUse)
                    ->with('project')
                    ->first();

                $projectCode = $active?->project?->code ?? 'project lain';

                throw BusinessRuleException::make(
                    "Asset sedang digunakan pada {$projectCode} dan belum dapat digunakan pada project lain."
                );
            }

            $assignment = ProjectAsset::query()->create([
                'project_id' => $project->id,
                'asset_id' => $asset->id,
                'assigned_at' => now(),
                'released_at' => null,
                'notes' => $validated['notes'] ?? null,
                'status' => ProjectAssetStatus::InUse,
                'assigned_by' => $actor->id,
                'released_by' => null,
            ]);

            $asset->status = AssetStatus::InUse;
            $asset->save();

            return $assignment->fresh(['asset', 'project']);
        });
    }
}
