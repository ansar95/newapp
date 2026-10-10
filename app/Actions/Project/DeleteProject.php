<?php

namespace App\Actions\Project;

use App\Actions\Asset\ReleaseAssetFromProject;
use App\Enums\ProjectAssetStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DeleteProject
{
    public const ERROR_BAG = 'projectDeletion';

    public function __construct(private ReleaseAssetFromProject $releaseAssetFromProject) {}

    /**
     * Soft deletes the project after releasing its active assets; BOQ, payment, procurement, billing and document data stay in the database.
     *
     * @param  array{confirmation_code?: string|null}  $data
     */
    public function handle(User $actor, Project $project, array $data): void
    {
        Validator::make($data, [
            'confirmation_code' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
                if (trim((string) $value) !== $project->code) {
                    $fail('Kode konfirmasi tidak sesuai dengan kode project.');
                }
            }],
        ], [
            'confirmation_code.required' => 'Ketik kode project untuk konfirmasi.',
        ])->validateWithBag(self::ERROR_BAG);

        DB::transaction(function () use ($actor, $project): void {
            $activeAssignments = $project->projectAssets()
                ->where('status', ProjectAssetStatus::InUse)
                ->get();

            foreach ($activeAssignments as $assignment) {
                $this->releaseAssetFromProject->handle($actor, $project, $assignment);
            }

            $project->delete();
        });
    }
}
