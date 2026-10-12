<?php

namespace App\Actions\ManPower;

use App\Exceptions\BusinessRuleException;
use App\Models\ManPower;
use App\Models\Project;
use App\Models\ProjectManPower;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssignManPowerToProject
{
    /**
     * Position and daily wage default to the master values but may be overridden per project.
     *
     * @param  array{man_power_id?: mixed, position?: string|null, daily_wage?: mixed, work_days?: mixed, start_date?: string|null, notes?: string|null}  $data
     */
    public function handle(User $actor, Project $project, array $data): ProjectManPower
    {
        $validated = Validator::make($data, [
            'man_power_id' => ['required', 'integer', 'exists:man_powers,id'],
            'position' => ['nullable', 'string', 'max:100'],
            'daily_wage' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
            'work_days' => ['required', 'integer', 'min:0', 'max:'.ManPowerRules::MAX_WORK_DAYS],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ManPowerRules::messages())->validate();

        if (! $project->acceptsManPowerAssignment()) {
            throw BusinessRuleException::make('Man power hanya dapat ditambahkan pada project berstatus ACTIVE.');
        }

        return DB::transaction(function () use ($actor, $project, $validated) {
            /** @var ManPower $manPower */
            $manPower = ManPower::query()->whereKey($validated['man_power_id'])->firstOrFail();

            if (! $manPower->is_active) {
                throw BusinessRuleException::make('Man power '.$manPower->name.' sudah tidak aktif.');
            }

            $alreadyAssigned = ProjectManPower::query()
                ->where('project_id', $project->id)
                ->where('man_power_id', $manPower->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyAssigned) {
                throw BusinessRuleException::make('Man power '.$manPower->name.' sudah terdaftar pada project ini.');
            }

            return ProjectManPower::query()->create([
                'project_id' => $project->id,
                'man_power_id' => $manPower->id,
                'position' => filled($validated['position'] ?? null) ? $validated['position'] : $manPower->position,
                'daily_wage' => $validated['daily_wage'] ?? $manPower->daily_wage,
                'work_days' => (int) $validated['work_days'],
                'start_date' => $validated['start_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }
}
