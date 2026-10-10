<?php

namespace App\Actions\Procurement;

use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdateProcurementRequest
{
    /**
     * Replaces the header and the full item list of a draft request. The request type cannot change.
     *
     * @param  array{
     *     request_date: string,
     *     supplier?: string|null,
     *     notes?: string|null,
     *     items: list<array<string, mixed>>
     * }  $data
     */
    public function handle(User $actor, ProcurementRequest $request, array $data): ProcurementRequest
    {
        $request->assertEditable();

        $data['items'] = array_values($data['items'] ?? []);

        $validated = Validator::make($data, ProcurementRules::request($request->type), ProcurementRules::messages())->validate();

        return DB::transaction(function () use ($request, $validated): ProcurementRequest {
            $request->fill([
                'request_date' => $validated['request_date'],
                'supplier' => $validated['supplier'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $request->save();

            $request->items()->delete();

            foreach ($validated['items'] as $item) {
                $request->items()->create(ProcurementRules::itemAttributes($request->type, $item));
            }

            $request->recalculateEstimatedTotal();

            return $request->fresh('items');
        });
    }
}
