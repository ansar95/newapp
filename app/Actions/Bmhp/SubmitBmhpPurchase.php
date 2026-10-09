<?php

namespace App\Actions\Bmhp;

use App\Enums\BmhpPurchaseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchase;
use App\Models\User;

class SubmitBmhpPurchase
{
    public function handle(User $actor, BmhpPurchase $purchase): BmhpPurchase
    {
        if ($purchase->status !== BmhpPurchaseStatus::Draft) {
            throw BusinessRuleException::make('Pembelian BMHP hanya dapat disubmit dari status DRAFT.');
        }

        $purchase->assertProjectAcceptsPurchases();

        if (! $purchase->details()->exists()) {
            throw BusinessRuleException::make('Pembelian BMHP harus memiliki minimal satu item sebelum disubmit.');
        }

        $purchase->recalculateTotal();

        $purchase->status = BmhpPurchaseStatus::Submitted;
        $purchase->submitted_by = $actor->id;
        $purchase->submitted_at = now();
        $purchase->save();

        return $purchase->refresh();
    }
}
