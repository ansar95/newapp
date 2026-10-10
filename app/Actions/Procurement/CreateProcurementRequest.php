<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Exceptions\BusinessRuleException;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateProcurementRequest
{
    /**
     * @param  array{
     *     type: string,
     *     request_date: string,
     *     supplier?: string|null,
     *     notes?: string|null,
     *     items: list<array<string, mixed>>
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data): ProcurementRequest
    {
        if (! ProcurementRequest::projectAcceptsRequests($project)) {
            throw BusinessRuleException::make('Permintaan barang hanya dapat dibuat pada project berstatus ACTIVE.');
        }

        Validator::make($data, [
            'type' => ['required', Rule::enum(ProcurementType::class)],
        ], ProcurementRules::messages())->validate();

        $type = ProcurementType::from($data['type']);
        $data['items'] = array_values($data['items'] ?? []);

        $validated = Validator::make($data, ProcurementRules::request($type), ProcurementRules::messages())->validate();

        return DB::transaction(function () use ($actor, $project, $type, $validated): ProcurementRequest {
            $request = ProcurementRequest::query()->create([
                'project_id' => $project->id,
                'request_number' => ProcurementRequest::nextRequestNumber(),
                'type' => $type,
                'request_date' => $validated['request_date'],
                'supplier' => $validated['supplier'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => ProcurementStatus::Draft,
                'created_by' => $actor->id,
            ]);

            foreach ($validated['items'] as $item) {
                $request->items()->create(ProcurementRules::itemAttributes($type, $item));
            }

            $request->recalculateEstimatedTotal();

            return $request->fresh('items');
        });
    }
}
