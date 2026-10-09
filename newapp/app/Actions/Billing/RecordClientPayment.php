<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Enums\ClientPaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\User;
use App\Support\ProjectBillingSummary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RecordClientPayment
{
    /**
     * @param  array{
     *     payment_date: string,
     *     amount: float|int|string,
     *     payment_number?: string|null,
     *     bank_reference?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $actor, ClientBilling $billing, array $data, ?UploadedFile $proof = null): ClientPayment
    {
        ClientBilling::assertProjectAcceptsBilling($billing->project);

        if ($billing->status !== ClientBillingStatus::Sent) {
            throw BusinessRuleException::make('Pembayaran client hanya dapat dicatat untuk penagihan yang sudah dikirim (SENT).');
        }

        $validated = Validator::make([...$data, 'proof_file' => $proof], [
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'payment_number' => ['nullable', 'string', 'max:50', 'unique:client_payments,payment_number'],
            'bank_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'proof_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'payment_date.required' => 'Tanggal pembayaran wajib diisi.',
            'amount.required' => 'Nilai pembayaran wajib diisi.',
            'amount.gt' => 'Nilai pembayaran harus lebih dari 0.',
            'payment_number.unique' => 'Nomor pembayaran sudah digunakan.',
            'proof_file.mimes' => 'Bukti pembayaran harus berupa PDF, JPG, atau PNG.',
            'proof_file.max' => 'Ukuran bukti pembayaran maksimal 5 MB.',
        ])->validate();

        return DB::transaction(function () use ($actor, $billing, $validated, $proof): ClientPayment {
            $billing = ClientBilling::query()->whereKey($billing->id)->lockForUpdate()->firstOrFail();

            $receivedCents = ProjectBillingSummary::cents($billing->payments()->where('status', ClientPaymentStatus::Received)->sum('amount'));
            $outstandingCents = ProjectBillingSummary::cents($billing->billing_amount) - $receivedCents;

            if (ProjectBillingSummary::cents($validated['amount']) > $outstandingCents) {
                throw BusinessRuleException::make(BillingMessages::PAYMENT_EXCEEDS_OUTSTANDING);
            }

            $paymentNumber = filled($validated['payment_number'] ?? null)
                ? $validated['payment_number']
                : ClientPayment::nextPaymentNumber();

            return ClientPayment::query()->create([
                'client_billing_id' => $billing->id,
                'payment_number' => $paymentNumber,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'bank_reference' => $validated['bank_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'proof_file' => $proof?->store('client-payments', 'public'),
                'status' => ClientPaymentStatus::Received,
                'created_by' => $actor->id,
            ]);
        });
    }
}
