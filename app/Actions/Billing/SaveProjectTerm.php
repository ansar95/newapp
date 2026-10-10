<?php

namespace App\Actions\Billing;

use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermReadiness;
use App\Enums\ProjectTermTriggerType;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SaveProjectTerm
{
    public function __construct(private SyncProjectTermAmounts $syncProjectTermAmounts) {}

    /**
     * Create a term, or update one when $term is given. The billing base value is the project contract value.
     *
     * @param  array{
     *     term_name: string,
     *     calculation_type: string,
     *     percentage?: float|int|string|null,
     *     amount?: float|int|string|null,
     *     trigger_type?: string|null,
     *     trigger_value?: string|null,
     *     trigger_progress?: float|int|string|null,
     *     due_date?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data, ?ProjectTerm $term = null): ProjectTerm
    {
        ClientBilling::assertProjectAcceptsBilling($project);

        if ($term !== null && ((int) $term->project_id !== (int) $project->id || $term->isCancelled())) {
            throw BusinessRuleException::make('Termin tidak dapat diubah.');
        }

        if ((float) ($project->contract_value ?? 0) <= 0) {
            throw BusinessRuleException::make('Isi nilai kontrak project terlebih dahulu sebelum membuat termin.');
        }

        $validated = Validator::make($data, [
            'term_name' => ['required', 'string', 'max:255'],
            'calculation_type' => ['required', Rule::enum(ProjectTermCalculationType::class)],
            'percentage' => ['nullable', 'required_if:calculation_type,'.ProjectTermCalculationType::Percentage->value, 'numeric', 'gt:0', 'max:100'],
            'amount' => ['nullable', 'required_if:calculation_type,'.ProjectTermCalculationType::FixedAmount->value, 'numeric', 'gt:0', 'max:9999999999999999'],
            'trigger_type' => ['nullable', Rule::enum(ProjectTermTriggerType::class)],
            'trigger_value' => ['nullable', 'string', 'max:255'],
            'trigger_progress' => ['nullable', 'required_if:trigger_type,'.ProjectTermTriggerType::Progress->value, 'numeric', 'gt:0', 'max:100'],
            'due_date' => ['nullable', 'required_if:trigger_type,'.ProjectTermTriggerType::Date->value, 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'trigger_progress.required_if' => 'Isi progress minimal (%) untuk syarat Progress Fisik.',
            'trigger_progress.gt' => 'Progress minimal harus lebih dari 0%.',
            'trigger_progress.max' => 'Progress minimal tidak boleh lebih dari 100%.',
            'due_date.required_if' => 'Isi Target Tanggal untuk syarat Tanggal.',
            'term_name.required' => 'Nama termin wajib diisi.',
            'percentage.required_if' => 'Persentase termin wajib diisi.',
            'percentage.gt' => 'Persentase termin harus lebih dari 0.',
            'percentage.max' => BillingMessages::TERM_PERCENTAGE_EXCEEDS,
            'amount.required_if' => 'Nilai termin wajib diisi.',
            'amount.gt' => 'Nilai termin harus lebih dari 0.',
        ])->validate();

        $calculationType = ProjectTermCalculationType::from($validated['calculation_type']);
        $triggerType = ProjectTermTriggerType::tryFrom($validated['trigger_type'] ?? '') ?? ProjectTermTriggerType::Manual;

        return DB::transaction(function () use ($actor, $project, $validated, $calculationType, $triggerType, $term): ProjectTerm {
            $term ??= new ProjectTerm([
                'project_id' => $project->id,
                'term_number' => (int) ProjectTerm::query()->where('project_id', $project->id)->lockForUpdate()->max('term_number') + 1,
                'readiness' => ProjectTermReadiness::NotReady,
                'created_by' => $actor->id,
            ]);

            $term->fill([
                'term_name' => $validated['term_name'],
                'calculation_type' => $calculationType,
                'percentage' => $calculationType === ProjectTermCalculationType::Percentage ? $validated['percentage'] : null,
                'amount' => $calculationType === ProjectTermCalculationType::FixedAmount ? $validated['amount'] : 0,
                'trigger_type' => $triggerType,
                'trigger_value' => $validated['trigger_value'] ?? null,
                'trigger_progress' => $triggerType === ProjectTermTriggerType::Progress ? $validated['trigger_progress'] : null,
                'due_date' => $validated['due_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->syncProjectTermAmounts->handle($project, $project->contract_value, $term);

            return $term->refresh();
        });
    }
}
