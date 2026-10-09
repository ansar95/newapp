<?php

namespace App\Actions\Bmhp;

use App\Models\BmhpPurchase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UpdateBmhpPurchase
{
    /**
     * @param  array{
     *     purchase_date: string,
     *     supplier: string,
     *     invoice_number?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $actor, BmhpPurchase $purchase, array $data, ?UploadedFile $receipt = null): BmhpPurchase
    {
        $purchase->assertEditable();

        $validated = Validator::make([...$data, 'receipt_file' => $receipt], BmhpPurchaseRules::header(), BmhpPurchaseRules::headerMessages())
            ->validate();

        $purchase->fill([
            'purchase_date' => $validated['purchase_date'],
            'supplier' => $validated['supplier'],
            'invoice_number' => $validated['invoice_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($receipt !== null) {
            $previousReceipt = $purchase->receipt_file;
            $purchase->receipt_file = $receipt->store('bmhp-receipts', 'public');

            if ($previousReceipt) {
                Storage::disk('public')->delete($previousReceipt);
            }
        }

        $purchase->save();

        return $purchase->refresh();
    }
}
