<?php

namespace App\Actions\Bmhp;

use App\Enums\BmhpPurchaseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchase;
use App\Models\User;

class ConfirmBmhpPurchase
{
    public function handle(User $actor, BmhpPurchase $purchase): BmhpPurchase
    {
        if ($purchase->status !== BmhpPurchaseStatus::Submitted) {
            throw BusinessRuleException::make('Pembelian BMHP hanya dapat dikonfirmasi dari status SUBMITTED.');
        }

        $purchase->assertProjectAcceptsPurchases();

        $purchase->status = BmhpPurchaseStatus::Confirmed;
        $purchase->confirmed_by = $actor->id;
        $purchase->confirmed_at = now();
        $purchase->save();

        return $purchase->refresh();
    }
}
