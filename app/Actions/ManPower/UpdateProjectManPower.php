<?php

namespace App\Actions\ManPower;

use App\Exceptions\BusinessRuleException;
use App\Models\ProjectManPower;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdateProjectManPower
{
    /**
     * @param  array{position?: string, daily_wage?: mixed, work_days?: mixed, start_date?: string|null, notes?: string|null}  $data
     */
    public function handle(ProjectManPower $projectManPower, array $data): ProjectManPower
    {
        $validated = Validator::make($data, [
            'position' => ['required', 'string', 'max:100'],
            'daily_wage' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'work_days' => ['required', 'integer', 'min:0', 'max:'.ManPowerRules::MAX_WORK_DAYS],
            'start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ManPowerRules::messages())->validate();

        $projectManPower->loadMissing('project');

        if (! $projectManPower->project->acceptsManPowerAssignment()) {
            throw BusinessRuleException::make('Data man power hanya dapat diubah pada project berstatus ACTIVE.');
        }

        return DB::transaction(function () use ($projectManPower, $validated) {
            /** @var ProjectManPower $locked */
            $locked = ProjectManPower::query()->whereKey($projectManPower->id)->lockForUpdate()->firstOrFail();

            $newSalary = ProjectManPower::salaryFor((int) $validated['work_days'], (float) $validated['daily_wage']);
            $paid = $locked->paidAmount();

            if ($newSalary < $paid) {
                throw BusinessRuleException::make(
                    'Total gaji baru ('.rupiah($newSalary).') tidak boleh lebih kecil dari kasbon + pelunasan yang sudah dibayar ('.rupiah($paid).').'
                );
            }

            $locked->update([
                'position' => $validated['position'],
                'daily_wage' => $validated['daily_wage'],
                'work_days' => (int) $validated['work_days'],
                'start_date' => $validated['start_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return $locked;
        });
    }
}
