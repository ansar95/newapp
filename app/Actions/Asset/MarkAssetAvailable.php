<?php

namespace App\Actions\Asset;

use App\Enums\AssetRequestStatus;
use App\Enums\AssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkAssetAvailable
{
    public function handle(User $actor, Asset $asset): Asset
    {
        return DB::transaction(function () use ($asset) {
            /** @var Asset $locked */
            $locked = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AssetStatus::Available) {
                return $locked;
            }

            if ($locked->status === AssetStatus::InUse) {
                throw BusinessRuleException::make(
                    'Asset yang sedang IN_USE harus di-release dari project terlebih dahulu.'
                );
            }

            if ($locked->status === AssetStatus::Inactive) {
                throw BusinessRuleException::make(
                    'Asset INACTIVE tidak dapat diubah menjadi AVAILABLE tanpa diaktifkan kembali.'
                );
            }

            $locked->status = AssetStatus::Available;

            if ($locked->request_status !== null && $locked->request_status !== AssetRequestStatus::Cancelled) {
                $locked->request_status = AssetRequestStatus::Purchased;
            }

            $locked->save();

            return $locked->refresh();
        });
    }
}
