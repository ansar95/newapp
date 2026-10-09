<?php

namespace App\Actions\Bmhp;

use App\Models\BmhpPurchaseDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveBmhpPurchaseDetail
{
    public function handle(User $actor, BmhpPurchaseDetail $detail): void
    {
        $purchase = $detail->purchase;
        $purchase->assertEditable();

        DB::transaction(function () use ($detail, $purchase): void {
            $detail->allocations()->delete();
            $detail->delete();
            $purchase->recalculateTotal();
        });
    }
}
