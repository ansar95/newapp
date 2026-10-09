<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateClientBilling
{
    /**
     * @param  array{
     *     project_term_id: int|string,
     *     billing_date: string,
     *     billing_amount: float|int|string,
     *     due_date?: string|null,
     *     description?: string|null
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data, ?UploadedFile $attachment = null): ClientBilling
    {
        ClientBilling::assertProjectAcceptsBilling($project);

        $validated = Validator::make(
            [...$data, 'attachment' => $attachment],
            ['project_term_id' => ['required', 'integer'], ...ClientBillingRules::form()],
            ['project_term_id.required' => 'Termin wajib dipilih.', ...ClientBillingRules::messages()],
        )->validate();

        $term = ProjectTerm::query()
            ->where('project_id', $project->id)
            ->whereKey($validated['project_term_id'])
            ->first();

        if ($term === null) {
            throw BusinessRuleException::make('Termin tidak ditemukan pada project ini.');
        }

        if (! $term->isReady()) {
            throw BusinessRuleException::make('Termin harus berstatus READY sebelum dapat ditagihkan.');
        }

        return DB::transaction(function () use ($actor, $project, $term, $validated, $attachment): ClientBilling {
            ClientBillingRules::assertFitsInTerm($term, $validated['billing_amount']);

            return ClientBilling::query()->create([
                'project_id' => $project->id,
                'project_term_id' => $term->id,
                'billing_number' => ClientBilling::nextBillingNumber(),
                'billing_date' => $validated['billing_date'],
                'billing_amount' => $validated['billing_amount'],
                'due_date' => $validated['due_date'] ?? null,
                'description' => $validated['description'] ?? null,
                'attachment' => $attachment?->store('client-billings', 'public'),
                'status' => ClientBillingStatus::Draft,
                'created_by' => $actor->id,
            ]);
        });
    }
}
