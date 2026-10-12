<?php

namespace App\Actions\Asset;

use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\ProjectAsset;
use Illuminate\Support\Facades\Validator;

class UpdateAssetResponsible
{
    /**
     * @param  array{responsible_name?: string|null, responsible_position?: string|null}  $data
     */
    public function handle(Project $project, ProjectAsset $assignment, array $data): ProjectAsset
    {
        $validated = Validator::make($data, [
            'responsible_name' => ['nullable', 'string', 'max:255'],
            'responsible_position' => ['nullable', 'string', 'max:255'],
        ], [
            'responsible_name.max' => 'Nama penanggung jawab maksimal 255 karakter.',
            'responsible_position.max' => 'Jabatan penanggung jawab maksimal 255 karakter.',
        ])->validate();

        if ((int) $assignment->project_id !== (int) $project->id) {
            throw BusinessRuleException::make('Asset tidak sedang digunakan pada project ini.');
        }

        if (! $assignment->isActive()) {
            throw BusinessRuleException::make('Penanggung jawab hanya dapat diubah selama asset masih digunakan.');
        }

        $assignment->update([
            'responsible_name' => $validated['responsible_name'] ?? null,
            'responsible_position' => $validated['responsible_position'] ?? null,
        ]);

        return $assignment;
    }
}
