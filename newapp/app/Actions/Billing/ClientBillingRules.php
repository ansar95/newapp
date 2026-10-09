<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\ProjectTerm;
use App\Support\ProjectBillingSummary;

class ClientBillingRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function form(): array
    {
        return [
            'billing_date' => ['required', 'date'],
            'billing_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'due_date' => ['nullable', 'date', 'after_or_equal:billing_date'],
            'description' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'billing_date.required' => 'Tanggal penagihan wajib diisi.',
            'billing_amount.required' => 'Nilai penagihan wajib diisi.',
            'billing_amount.gt' => 'Nilai penagihan harus lebih dari 0.',
            'due_date.after_or_equal' => 'Jatuh tempo tidak boleh sebelum tanggal penagihan.',
            'attachment.mimes' => 'Lampiran harus berupa PDF, JPG, atau PNG.',
            'attachment.max' => 'Ukuran lampiran maksimal 5 MB.',
        ];
    }

    /**
     * Lock the term and make sure the amount fits in what is left of it.
     * Every non-cancelled billing (draft included) holds part of the term.
     */
    public static function assertFitsInTerm(ProjectTerm $term, float|int|string $amount, ?ClientBilling $ignore = null): void
    {
        $term = ProjectTerm::query()->whereKey($term->id)->lockForUpdate()->firstOrFail();

        $reservedCents = ProjectBillingSummary::cents(ClientBilling::query()
            ->where('project_term_id', $term->id)
            ->where('status', '!=', ClientBillingStatus::Cancelled)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->id))
            ->sum('billing_amount'));

        if (ProjectBillingSummary::cents($amount) > ProjectBillingSummary::cents($term->amount) - $reservedCents) {
            throw BusinessRuleException::make(BillingMessages::BILLING_EXCEEDS_REMAINING);
        }
    }
}
