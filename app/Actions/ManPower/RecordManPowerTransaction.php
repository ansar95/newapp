<?php

namespace App\Actions\ManPower;

use App\Enums\ManPowerTransactionStatus;
use App\Enums\ManPowerTransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\ManPowerTransaction;
use App\Models\ProjectManPower;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RecordManPowerTransaction
{
    /**
     * Kasbon and payouts are project expenses; together they may never exceed the worker's total salary.
     *
     * @param  array{type?: string, transaction_date?: string, amount?: mixed, notes?: string|null}  $data
     */
    public function handle(User $actor, ProjectManPower $projectManPower, array $data): ManPowerTransaction
    {
        $validated = Validator::make($data, [
            'type' => ['required', Rule::enum(ManPowerTransactionType::class)],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ManPowerRules::messages())->validate();

        $projectManPower->loadMissing('project');

        if (! $projectManPower->project->acceptsManPowerPayment()) {
            throw BusinessRuleException::make('Kasbon / pelunasan hanya dapat dicatat pada project berstatus ACTIVE atau COMPLETED.');
        }

        return DB::transaction(function () use ($actor, $projectManPower, $validated) {
            /** @var ProjectManPower $locked */
            $locked = ProjectManPower::query()->whereKey($projectManPower->id)->lockForUpdate()->firstOrFail();

            $amount = round((float) $validated['amount'], 2);
            $remaining = $locked->remainingSalary();

            if ($amount > $remaining) {
                throw BusinessRuleException::make(
                    'Nominal '.rupiah($amount).' melebihi sisa gaji yang belum dibayar ('.rupiah($remaining).').'
                );
            }

            return ManPowerTransaction::query()->create([
                'project_man_power_id' => $locked->id,
                'type' => $validated['type'],
                'transaction_date' => $validated['transaction_date'],
                'amount' => $amount,
                'notes' => $validated['notes'] ?? null,
                'status' => ManPowerTransactionStatus::Paid,
                'created_by' => $actor->id,
            ]);
        });
    }
}
