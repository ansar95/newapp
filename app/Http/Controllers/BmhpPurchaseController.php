<?php

namespace App\Http\Controllers;

use App\Actions\Bmhp\CancelBmhpPurchase;
use App\Actions\Bmhp\ConfirmBmhpPurchase;
use App\Actions\Bmhp\ReturnBmhpPurchaseToDraft;
use App\Actions\Bmhp\SubmitBmhpPurchase;
use App\Actions\Bmhp\UpdateBmhpPurchase;
use App\Enums\BoqStatus;
use App\Enums\MasterBmhpStatus;
use App\Models\BmhpPurchase;
use App\Models\MasterBmhp;
use App\Support\ProjectActualCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BmhpPurchaseController extends Controller
{
    public function show(Request $request, BmhpPurchase $bmhpPurchase): View
    {
        $this->authorize('view', $bmhpPurchase);

        $bmhpPurchase->load([
            'project',
            'creator',
            'submitter',
            'confirmer',
            'canceller',
            'details.masterBmhp',
            'details.allocations.boqItem.cogs.boq',
        ]);

        $project = $bmhpPurchase->project;
        $canAllocate = $request->user()->can('allocate', $bmhpPurchase);

        $approvedBoqs = $canAllocate
            ? $project->boqs()->where('status', BoqStatus::Approved)->with('cogs.items')->orderBy('id')->get()
            : collect();

        return view('bmhp-purchases.show', [
            'purchase' => $bmhpPurchase,
            'project' => $project,
            'canAllocate' => $canAllocate,
            'approvedBoqs' => $approvedBoqs,
            'actualCost' => $canAllocate ? ProjectActualCost::forProject($project) : null,
            'masterBmhps' => $request->user()->can('update', $bmhpPurchase)
                ? MasterBmhp::query()->where('status', MasterBmhpStatus::Active)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function edit(BmhpPurchase $bmhpPurchase): View
    {
        $this->authorize('update', $bmhpPurchase);

        return view('bmhp-purchases.edit', [
            'purchase' => $bmhpPurchase->load('project'),
            'project' => $bmhpPurchase->project,
        ]);
    }

    public function update(Request $request, BmhpPurchase $bmhpPurchase, UpdateBmhpPurchase $updateBmhpPurchase): RedirectResponse
    {
        $this->authorize('update', $bmhpPurchase);

        $updateBmhpPurchase->handle(
            $request->user(),
            $bmhpPurchase,
            $request->only(['purchase_date', 'supplier', 'invoice_number', 'notes']),
            $request->file('receipt_file')
        );

        return redirect()
            ->route('bmhp-purchases.show', $bmhpPurchase)
            ->with('success', 'Header pembelian berhasil diperbarui.');
    }

    public function submit(Request $request, BmhpPurchase $bmhpPurchase, SubmitBmhpPurchase $submitBmhpPurchase): RedirectResponse
    {
        $this->authorize('submit', $bmhpPurchase);

        $submitBmhpPurchase->handle($request->user(), $bmhpPurchase);

        return back()->with('success', 'Pembelian disubmit dan menunggu konfirmasi.');
    }

    public function confirm(Request $request, BmhpPurchase $bmhpPurchase, ConfirmBmhpPurchase $confirmBmhpPurchase): RedirectResponse
    {
        $this->authorize('confirm', $bmhpPurchase);

        $confirmBmhpPurchase->handle($request->user(), $bmhpPurchase);

        return back()->with('success', 'Pembelian dikonfirmasi dan sudah masuk ke actual cost.');
    }

    public function returnToDraft(Request $request, BmhpPurchase $bmhpPurchase, ReturnBmhpPurchaseToDraft $returnBmhpPurchaseToDraft): RedirectResponse
    {
        $this->authorize('returnToDraft', $bmhpPurchase);

        $returnBmhpPurchaseToDraft->handle($request->user(), $bmhpPurchase);

        return back()->with('success', 'Pembelian dikembalikan ke DRAFT untuk diperbaiki.');
    }

    public function cancel(Request $request, BmhpPurchase $bmhpPurchase, CancelBmhpPurchase $cancelBmhpPurchase): RedirectResponse
    {
        $this->authorize('cancel', $bmhpPurchase);

        $cancelBmhpPurchase->handle($request->user(), $bmhpPurchase, $request->only('cancel_reason'));

        return back()->with('success', 'Pembelian dibatalkan dan tidak dihitung sebagai actual.');
    }

    public function receipt(BmhpPurchase $bmhpPurchase): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $bmhpPurchase);

        if (! $bmhpPurchase->receipt_file || ! Storage::disk('public')->exists($bmhpPurchase->receipt_file)) {
            return back()->with('error', 'File bukti pembelian tidak ditemukan.');
        }

        return Storage::disk('public')->download($bmhpPurchase->receipt_file);
    }
}
