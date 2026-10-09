<?php

namespace App\Actions\Asset;

use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use App\Models\Asset;
use App\Models\Project;

class CanAssignAsset
{
    /**
     * @return array{allowed: bool, message: string|null}
     */
    public function evaluate(Asset $asset, Project $project): array
    {
        $asset->loadMissing('activeAssignment.project');

        if ($asset->trashed()) {
            return [
                'allowed' => false,
                'message' => 'Asset tidak ditemukan.',
            ];
        }

        if ($asset->status === AssetStatus::Inactive) {
            return [
                'allowed' => false,
                'message' => 'Asset INACTIVE tidak dapat digunakan pada project.',
            ];
        }

        if ($asset->status === AssetStatus::Requested) {
            return [
                'allowed' => false,
                'message' => 'Asset masih berstatus REQUESTED dan belum tersedia untuk digunakan.',
            ];
        }

        if ($asset->status !== AssetStatus::Available) {
            $active = $asset->activeAssignment;
            if ($active !== null && $active->project !== null) {
                return [
                    'allowed' => false,
                    'message' => "Asset sedang digunakan pada {$active->project->code} dan belum dapat digunakan pada project lain.",
                ];
            }

            return [
                'allowed' => false,
                'message' => 'Asset tidak berstatus AVAILABLE dan belum dapat digunakan.',
            ];
        }

        $activeAssignment = $asset->projectAssets()
            ->where('status', ProjectAssetStatus::InUse)
            ->with('project')
            ->first();

        if ($activeAssignment !== null) {
            $projectCode = $activeAssignment->project?->code ?? 'project lain';

            if ((int) $activeAssignment->project_id === (int) $project->id) {
                return [
                    'allowed' => false,
                    'message' => "Asset sudah digunakan pada {$projectCode}.",
                ];
            }

            return [
                'allowed' => false,
                'message' => "Asset sedang digunakan pada {$projectCode} dan belum dapat digunakan pada project lain.",
            ];
        }

        return [
            'allowed' => true,
            'message' => null,
        ];
    }

    public function handle(Asset $asset, Project $project): bool
    {
        return $this->evaluate($asset, $project)['allowed'];
    }
}
