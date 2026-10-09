<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SendClientBilling
{
    /**
     * Mark the billing as sent to the client. From here on it counts as billed.
     *
     * @param  array{invoice_number: string, invoice_date: string, due_date?: string|null}  $data
     */
    public function handle(User $actor, ClientBilling $billing, array $data): ClientBilling
    {
        ClientBilling::assertProjectAcceptsBilling($billing->project);

        if ($billing->status !== ClientBillingStatus::Submitted) {
            throw BusinessRuleException::make('Hanya penagihan berstatus SUBMITTED yang dapat dikirim ke client.');
        }

        $validated = Validator::make($data, [
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('client_billings', 'invoice_number')->ignore($billing->id)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
        ], [
            'invoice_number.required' => 'Nomor invoice wajib diisi saat penagihan dikirim.',
            'invoice_number.unique' => 'Nomor invoice sudah digunakan.',
            'invoice_date.required' => 'Tanggal invoice wajib diisi.',
            'due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal invoice.',
        ])->validate();

        $billing->forceFill([
            'invoice_number' => $validated['invoice_number'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'] ?? $billing->due_date,
            'status' => ClientBillingStatus::Sent,
            'sent_by' => $actor->id,
            'sent_at' => now(),
        ])->save();

        return $billing->refresh();
    }
}
