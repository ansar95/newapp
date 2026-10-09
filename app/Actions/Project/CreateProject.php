<?php

namespace App\Actions\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateProject
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     client_name: string,
     *     contract_number?: string|null,
     *     contract_value?: numeric-string|float|int|null,
     *     description?: string|null,
     *     start_date?: string|null,
     *     target_end_date?: string|null
     * }  $data
     */
    public function handle(User $actor, array $data): Project
    {
        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:50', 'unique:projects,code'],
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], [
            'code.required' => 'Project harus memiliki code.',
            'code.unique' => 'Project code harus unik.',
            'name.required' => 'Project harus memiliki name.',
            'client_name.required' => 'Project harus memiliki client name.',
            'contract_number.max' => 'Nomor kontrak maksimal 100 karakter.',
            'contract_value.numeric' => 'Nilai kontrak harus berupa angka.',
            'contract_value.min' => 'Nilai kontrak tidak boleh negatif.',
            'contract_value.max' => 'Nilai kontrak terlalu besar.',
        ])->validate();

        return Project::query()->create([
            ...$validated,
            'status' => ProjectStatus::Draft,
            'created_by' => $actor->id,
        ]);
    }
}
