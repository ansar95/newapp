<?php

namespace App\Actions\ManPower;

use App\Models\ManPower;
use Illuminate\Support\Facades\Validator;

class UpdateManPower
{
    /**
     * Changing the master daily wage does not touch wages already copied into projects.
     *
     * @param  array{name?: string, position?: string, daily_wage?: mixed, phone?: string|null, notes?: string|null, is_active?: mixed}  $data
     */
    public function handle(ManPower $manPower, array $data): ManPower
    {
        $validated = Validator::make($data, [
            ...ManPowerRules::master(),
            'is_active' => ['required', 'boolean'],
        ], ManPowerRules::messages())->validate();

        $manPower->update([
            'name' => $validated['name'],
            'position' => $validated['position'],
            'daily_wage' => $validated['daily_wage'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return $manPower;
    }
}
