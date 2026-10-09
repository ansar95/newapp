<?php

namespace App\Actions\Bmhp;

use App\Enums\BmhpPurchaseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchase;
use App\Models\User;

class ReturnBmhpPurchaseToDraft
{
    public function handle(User $actor, BmhpPurchase $purchase): BmhpPurchase
    {
        if ($purchase->status !== BmhpPurchaseStatus::Submitted) {
            throw BusinessRuleException::make('Hanya pembelian berstatus SUBMITTED yang dapat dikembalikan ke DRAFT.');
        }

        $purchase->assertProjectAcceptsPurchases();

        $purchase->status = BmhpPurchaseStatus::Draft;
        $purchase->submitted_by = null;
        $purchase->submitted_at = null;
        $purchase->save();

        return $purchase->refresh();
    }
}
