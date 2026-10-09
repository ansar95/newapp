<?php

namespace App\Http\Controllers;

use App\Actions\Bmhp\AllocateBmhpPurchaseDetail;
use App\Actions\Bmhp\RemoveBmhpPurchaseDetail;
use App\Actions\Bmhp\SaveBmhpPurchaseDetail;
use App\Models\BmhpPurchase;
use App\Models\BmhpPurchaseDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BmhpPurchaseDetailController extends Controller
{
    public function store(Request $request, BmhpPurchase $bmhpPurchase, SaveBmhpPurchaseDetail $saveBmhpPurchaseDetail): RedirectResponse
    {
        $this->authorize('update', $bmhpPurchase);

        $saveBmhpPurchaseDetail->handle(
            $request->user(),
            $bmhpPurchase,
            $request->only(['master_bmhp_id', 'quantity', 'unit_price', 'notes'])
        );

        return back()->with('success', 'Item BMHP ditambahkan.');
    }

    public function update(Request $request, BmhpPurchaseDetail $detail, SaveBmhpPurchaseDetail $saveBmhpPurchaseDetail): RedirectResponse
    {
        $this->authorize('update', $detail->purchase);

        $saveBmhpPurchaseDetail->handle(
            $request->user(),
            $detail->purchase,
            $request->only(['master_bmhp_id', 'quantity', 'unit_price', 'notes']),
            $detail
        );

        return back()->with('success', 'Item BMHP diperbarui.');
    }

    public function destroy(Request $request, BmhpPurchaseDetail $detail, RemoveBmhpPurchaseDetail $removeBmhpPurchaseDetail): RedirectResponse
    {
        $this->authorize('update', $detail->purchase);

        $removeBmhpPurchaseDetail->handle($request->user(), $detail);

        return back()->with('success', 'Item BMHP dihapus beserta alokasinya.');
    }

    public function allocate(Request $request, BmhpPurchaseDetail $detail, AllocateBmhpPurchaseDetail $allocateBmhpPurchaseDetail): RedirectResponse
    {
        $this->authorize('allocate', $detail->purchase);

        $rows = collect($request->input('allocations', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['selected']))
            ->map(fn (array $row) => [
                'boq_item_id' => $row['boq_item_id'] ?? null,
                'allocated_amount' => $row['allocated_amount'] ?? null,
                'allocated_quantity' => $row['allocated_quantity'] ?? null,
                'notes' => $row['notes'] ?? null,
            ])
            ->values()
            ->all();

        $allocateBmhpPurchaseDetail->handle($request->user(), $detail, $rows);

        return back()->with('success', 'Alokasi '.$detail->item_name.' ke Item BOQ disimpan.');
    }
}
