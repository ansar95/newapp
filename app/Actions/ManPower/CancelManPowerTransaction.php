<?php

namespace App\Actions\ManPower;

use App\Enums\ManPowerTransactionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ManPowerTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CancelManPowerTransaction
{
    /**
     * Cancelled transactions stay in the history but no longer count as project expense.
     *
     * @param  array{cancel_reason?: string}  $data
     */
    public function handle(User $actor, ManPowerTransaction $transaction, array $data): ManPowerTransaction
    {
        $transaction->loadMissing('projectManPower.project');

        if (! $transaction->projectManPower->project->acceptsManPowerPayment()) {
            throw BusinessRuleException::make('Transaksi hanya dapat dibatalkan pada project berstatus ACTIVE atau COMPLETED.');
        }

        if (! $transaction->isPaid()) {
            throw BusinessRuleException::make('Transaksi sudah dibatalkan.');
        }

        $validated = Validator::make($data, [
            'cancel_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], ManPowerRules::messages())->validate();

        $transaction->forceFill([
            'status' => ManPowerTransactionStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['cancel_reason'],
        ])->save();

        return $transaction->refresh();
    }
}
