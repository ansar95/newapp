<?php

namespace App\Actions\Bmhp;

use App\Enums\MasterBmhpStatus;
use App\Models\MasterBmhp;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateMasterBmhp
{
    /**
     * @param  array{
     *     code: string,
     *     name: string,
     *     category: string,
     *     unit: string,
     *     description?: string|null,
     *     status: string
     * }  $data
     */
    public function handle(User $actor, MasterBmhp $masterBmhp, array $data): MasterBmhp
    {
        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:50', Rule::unique('master_bmhps', 'code')->ignore($masterBmhp->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(MasterBmhp::categoryOptions())],
            'unit' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(MasterBmhpStatus::class)],
        ], [
            'code.unique' => 'Kode BMHP sudah digunakan.',
            'name.required' => 'Nama BMHP wajib diisi.',
            'category.in' => 'Kategori BMHP tidak valid.',
            'unit.required' => 'Satuan BMHP wajib diisi.',
        ])->validate();

        $masterBmhp->fill($validated);
        $masterBmhp->save();

        return $masterBmhp->refresh();
    }
}
