<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Enums\ClientPaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CancelClientBilling
{
    /**
     * Cancelled billings stay in the history but no longer count as billed nor hold term value.
     *
     * @param  array{cancel_reason: string}  $data
     */
    public function handle(User $actor, ClientBilling $billing, array $data): ClientBilling
    {
        ClientBilling::assertProjectAcceptsBilling($billing->project);

        if ($billing->isCancelled()) {
            throw BusinessRuleException::make('Penagihan sudah dibatalkan.');
        }

        if ($billing->payments()->where('status', ClientPaymentStatus::Received)->exists()) {
            throw BusinessRuleException::make('Penagihan yang sudah menerima pembayaran tidak dapat dibatalkan. Batalkan pembayaran client terlebih dahulu.');
        }

        $validated = Validator::make($data, [
            'cancel_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan minimal 3 karakter.',
        ])->validate();

        $billing->forceFill([
            'status' => ClientBillingStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['cancel_reason'],
        ])->save();

        return $billing->refresh();
    }
}
