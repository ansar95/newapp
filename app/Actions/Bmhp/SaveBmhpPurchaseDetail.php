<?php

namespace App\Actions\Bmhp;

use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchase;
use App\Models\BmhpPurchaseDetail;
use App\Models\MasterBmhp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaveBmhpPurchaseDetail
{
    /**
     * Add a new detail line, or update an existing one when $detail is given.
     *
     * @param  array{
     *     master_bmhp_id: int|string,
     *     quantity: float|int|string,
     *     unit_price: float|int|string,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $actor, BmhpPurchase $purchase, array $data, ?BmhpPurchaseDetail $detail = null): BmhpPurchaseDetail
    {
        $purchase->assertEditable();

        if ($detail !== null && (int) $detail->bmhp_purchase_id !== (int) $purchase->id) {
            throw BusinessRuleException::make('Detail pembelian tidak termasuk dalam pembelian ini.');
        }

        $validated = Validator::make($data, [
            'master_bmhp_id' => ['required', 'integer', 'exists:master_bmhps,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'notes' => ['nullable', 'string'],
        ], [
            'master_bmhp_id.required' => 'BMHP wajib dipilih dari Master BMHP.',
            'master_bmhp_id.exists' => 'BMHP wajib dipilih dari Master BMHP.',
            'quantity.required' => 'Quantity wajib diisi.',
            'quantity.gt' => 'Quantity harus lebih dari 0.',
            'unit_price.required' => 'Harga satuan wajib diisi.',
            'unit_price.min' => 'Harga satuan tidak boleh kurang dari 0.',
        ])->validate();

        /** @var MasterBmhp $masterBmhp */
        $masterBmhp = MasterBmhp::query()->findOrFail($validated['master_bmhp_id']);

        $isChangingMaster = $detail === null || (int) $detail->master_bmhp_id !== (int) $masterBmhp->id;

        if ($isChangingMaster && ! $masterBmhp->isActive()) {
            throw BusinessRuleException::make('Master BMHP berstatus INACTIVE tidak dapat digunakan pada pembelian baru.');
        }

        return DB::transaction(function () use ($purchase, $detail, $validated, $masterBmhp, $isChangingMaster): BmhpPurchaseDetail {
            $detail ??= new BmhpPurchaseDetail(['bmhp_purchase_id' => $purchase->id]);

            $detail->fill([
                'master_bmhp_id' => $masterBmhp->id,
                'quantity' => $validated['quantity'],
                'unit_price' => $validated['unit_price'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($isChangingMaster) {
                $detail->item_name = $masterBmhp->name;
                $detail->unit = $masterBmhp->unit;
            }

            if ($detail->exists) {
                $allocatedCents = BmhpAllocationMath::toCents(
                    (float) $detail->allocations()->lockForUpdate()->sum('allocated_amount')
                );

                if ($allocatedCents > BmhpAllocationMath::toCents((float) $detail->calculateTotal())) {
                    throw BusinessRuleException::make(BmhpAllocationMath::EXCEEDS_MESSAGE);
                }
            }

            $detail->save();
            $purchase->recalculateTotal();

            return $detail->refresh();
        });
    }
}
