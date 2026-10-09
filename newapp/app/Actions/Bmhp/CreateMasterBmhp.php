<?php

namespace App\Actions\Bmhp;

use App\Enums\MasterBmhpStatus;
use App\Models\MasterBmhp;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateMasterBmhp
{
    /**
     * @param  array{
     *     code?: string|null,
     *     name: string,
     *     category: string,
     *     unit: string,
     *     description?: string|null,
     *     status?: string|null
     * }  $data
     */
    public function handle(User $actor, array $data): MasterBmhp
    {
        if (blank($data['code'] ?? null)) {
            $data['code'] = MasterBmhp::nextCode();
        }

        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:50', Rule::unique('master_bmhps', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(MasterBmhp::categoryOptions())],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(MasterBmhpStatus::class)],
        ], [
            'code.unique' => 'Kode BMHP sudah digunakan.',
            'name.required' => 'Nama BMHP wajib diisi.',
            'category.in' => 'Kategori BMHP tidak valid.',
            'unit.required' => 'Satuan BMHP wajib diisi.',
        ])->validate();

        return MasterBmhp::query()->create([
            ...$validated,
            'status' => $validated['status'] ?? MasterBmhpStatus::Active->value,
            'created_by' => $actor->id,
        ]);
    }
}
