<?php

namespace App\Actions\Billing;

use App\Enums\ClientPaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CancelClientPayment
{
    /**
     * Cancelled payments stay in the history but no longer count as received.
     *
     * @param  array{cancel_reason: string}  $data
     */
    public function handle(User $actor, ClientPayment $payment, array $data): ClientPayment
    {
        $payment->loadMissing('billing.project');
        ClientBilling::assertProjectAcceptsBilling($payment->billing->project);

        if (! $payment->isReceived()) {
            throw BusinessRuleException::make('Pembayaran client sudah dibatalkan.');
        }

        $validated = Validator::make($data, [
            'cancel_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan minimal 3 karakter.',
        ])->validate();

        $payment->forceFill([
            'status' => ClientPaymentStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['cancel_reason'],
        ])->save();

        return $payment->refresh();
    }
}
