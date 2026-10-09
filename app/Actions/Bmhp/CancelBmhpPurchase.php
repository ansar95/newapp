<?php

namespace App\Actions\Bmhp;

use App\Enums\BmhpPurchaseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CancelBmhpPurchase
{
    /**
     * Cancelling keeps every detail and allocation for audit; only CONFIRMED purchases count as actual.
     *
     * @param  array{cancel_reason: string}  $data
     */
    public function handle(User $actor, BmhpPurchase $purchase, array $data): BmhpPurchase
    {
        if ($purchase->status === BmhpPurchaseStatus::Cancelled) {
            throw BusinessRuleException::make('Pembelian BMHP sudah berstatus CANCELLED.');
        }

        $purchase->assertProjectAcceptsPurchases();

        $validated = Validator::make($data, [
            'cancel_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
            'cancel_reason.min' => 'Alasan pembatalan wajib diisi.',
        ])->validate();

        $purchase->status = BmhpPurchaseStatus::Cancelled;
        $purchase->cancelled_by = $actor->id;
        $purchase->cancelled_at = now();
        $purchase->cancel_reason = $validated['cancel_reason'];
        $purchase->save();

        return $purchase->refresh();
    }
}
