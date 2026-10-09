<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateCogs
{
    /**
     * @param  array{
     *     code?: string|null,
     *     name: string,
     *     description?: string|null,
     *     sort_order?: int
     * }  $data
     */
    public function handle(User $actor, Boq $boq, array $data): BoqCogs
    {
        $this->assertBoqEditable($boq);

        $validated = Validator::make($data, [
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ])->validate();

        $code = filled($validated['code'] ?? null)
            ? $validated['code']
            : $boq->nextCogsCode();

        if ($boq->cogs()->where('code', $code)->exists()) {
            throw BusinessRuleException::make('Kode COGS sudah digunakan pada BOQ ini.');
        }

        return $boq->cogs()->create([
            'code' => $code,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? (($boq->cogs()->max('sort_order') ?? 0) + 1),
        ]);
    }

    private function assertBoqEditable(Boq $boq): void
    {
        if ($boq->status !== BoqStatus::Draft) {
            throw BusinessRuleException::make(
                'COGS hanya dapat ditambahkan ketika BOQ berstatus DRAFT. BOQ yang sudah APPROVED tidak boleh diedit secara bebas.'
            );
        }
    }
}
