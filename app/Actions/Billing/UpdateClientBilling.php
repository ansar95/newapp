<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UpdateClientBilling
{
    /**
     * @param  array{
     *     billing_date: string,
     *     billing_amount: float|int|string,
     *     due_date?: string|null,
     *     description?: string|null
     * }  $data
     */
    public function handle(User $actor, ClientBilling $billing, array $data, ?UploadedFile $attachment = null): ClientBilling
    {
        ClientBilling::assertProjectAcceptsBilling($billing->project);

        if ($billing->status !== ClientBillingStatus::Draft) {
            throw BusinessRuleException::make('Hanya penagihan berstatus DRAFT yang dapat diubah.');
        }

        $validated = Validator::make([...$data, 'attachment' => $attachment], ClientBillingRules::form(), ClientBillingRules::messages())
            ->validate();

        return DB::transaction(function () use ($billing, $validated, $attachment): ClientBilling {
            ClientBillingRules::assertFitsInTerm($billing->term, $validated['billing_amount'], $billing);

            $billing->fill([
                'billing_date' => $validated['billing_date'],
                'billing_amount' => $validated['billing_amount'],
                'due_date' => $validated['due_date'] ?? null,
                'description' => $validated['description'] ?? null,
            ]);

            if ($attachment !== null) {
                $billing->attachment = $attachment->store('client-billings', 'public');
            }

            $billing->save();

            return $billing->refresh();
        });
    }
}
