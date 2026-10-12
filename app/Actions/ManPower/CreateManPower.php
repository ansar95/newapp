<?php

namespace App\Actions\ManPower;

use App\Models\ManPower;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateManPower
{
    /**
     * @param  array{name?: string, position?: string, daily_wage?: mixed, phone?: string|null, notes?: string|null}  $data
     */
    public function handle(User $actor, array $data): ManPower
    {
        $validated = Validator::make($data, ManPowerRules::master(), ManPowerRules::messages())->validate();

        return DB::transaction(fn () => ManPower::query()->create([
            'code' => ManPower::nextCode(),
            'name' => $validated['name'],
            'position' => $validated['position'],
            'daily_wage' => $validated['daily_wage'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $actor->id,
        ]));
    }
}
