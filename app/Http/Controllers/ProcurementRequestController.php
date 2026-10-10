<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\ApproveProcurementRequest;
use App\Actions\Procurement\CancelProcurementRequest;
use App\Actions\Procurement\PayProcurementRequest;
use App\Actions\Procurement\ReturnProcurementRequestToDraft;
use App\Actions\Procurement\SubmitProcurementRequest;
use App\Actions\Procurement\UpdateProcurementRequest;
use App\Enums\ProcurementType;
use App\Models\Asset;
use App\Models\ProcurementRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementRequestController extends Controller
{
    public function show(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('view', $procurementRequest);

        $procurementRequest->load([
            'project',
            'creator',
            'submitter',
            'approver',
            'returner',
            'payer',
            'canceller',
            'items.allocations.boqItem',
            'items.assets',
        ]);

        return view('procurement-requests.show', [
            'procurementRequest' => $procurementRequest,
            'project' => $procurementRequest->project,
        ]);
    }

    public function edit(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('update', $procurementRequest);

        return view('procurement-requests.edit', [
            'procurementRequest' => $procurementRequest->load('items', 'project'),
            'project' => $procurementRequest->project,
            'assetCategories' => Asset::categoryOptions(),
        ]);
    }

    public function update(Request $request, ProcurementRequest $procurementRequest, UpdateProcurementRequest $updateProcurementRequest): RedirectResponse
    {
        $this->authorize('update', $procurementRequest);

        $updateProcurementRequest->handle(
            $request->user(),
            $procurementRequest,
            $request->only(['request_date', 'supplier', 'notes', 'items'])
        );

        return redirect()
            ->route('procurement-requests.show', $procurementRequest)
            ->with('success', 'Permintaan berhasil diperbarui.');
    }

    public function submit(Request $request, ProcurementRequest $procurementRequest, SubmitProcurementRequest $submitProcurementRequest): RedirectResponse
    {
        $this->authorize('submit', $procurementRequest);

        $submitProcurementRequest->handle($request->user(), $procurementRequest);

        return back()->with('success', 'Permintaan diajukan dan menunggu persetujuan Approver.');
    }

    public function approve(Request $request, ProcurementRequest $procurementRequest, ApproveProcurementRequest $approveProcurementRequest): RedirectResponse
    {
        $this->authorize('approve', $procurementRequest);

        $approveProcurementRequest->handle($request->user(), $procurementRequest);

        return back()->with('success', 'Permintaan disetujui dan menunggu pembayaran oleh Finance.');
    }

    public function returnToDraft(Request $request, ProcurementRequest $procurementRequest, ReturnProcurementRequestToDraft $returnProcurementRequestToDraft): RedirectResponse
    {
        $this->authorize('returnToDraft', $procurementRequest);

        $returnProcurementRequestToDraft->handle($request->user(), $procurementRequest, $request->only('return_note'));

        return back()->with('success', 'Permintaan dikembalikan ke Draft untuk diperbaiki.');
    }

    public function cancel(Request $request, ProcurementRequest $procurementRequest, CancelProcurementRequest $cancelProcurementRequest): RedirectResponse
    {
        $this->authorize('cancel', $procurementRequest);

        $cancelProcurementRequest->handle($request->user(), $procurementRequest, $request->only('cancel_reason'));

        return back()->with('success', 'Permintaan dibatalkan.');
    }

    public function payForm(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('pay', $procurementRequest);

        return view('procurement-requests.pay', [
            'procurementRequest' => $procurementRequest->load('items', 'project'),
            'project' => $procurementRequest->project,
        ]);
    }

    public function pay(Request $request, ProcurementRequest $procurementRequest, PayProcurementRequest $payProcurementRequest): RedirectResponse
    {
        $this->authorize('pay', $procurementRequest);

        $paid = $payProcurementRequest->handle(
            $request->user(),
            $procurementRequest,
            $request->only(['payment_date', 'invoice_number', 'items']),
            $request->file('payment_proof_file')
        );

        $message = match ($paid->type) {
            ProcurementType::Asset => 'Pembayaran dicatat. '.$paid->items->sum(fn ($item) => $item->assets->count()).' aset baru tercatat dan ditugaskan ke project ini.',
            ProcurementType::Bmhp => 'Pembayaran dicatat. Alokasikan item ke Item BOQ melalui tab BMHP.',
            ProcurementType::Rental => 'Pembayaran dicatat. Alokasikan sewa alat ke Item BOQ melalui tab Sewa Alat.',
        };

        return redirect()
            ->route('procurement-requests.show', $paid)
            ->with('success', $message);
    }

    public function paymentProof(ProcurementRequest $procurementRequest): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $procurementRequest);

        if (! $procurementRequest->payment_proof_file || ! Storage::disk('public')->exists($procurementRequest->payment_proof_file)) {
            return back()->with('error', 'File bukti bayar tidak ditemukan.');
        }

        return Storage::disk('public')->download($procurementRequest->payment_proof_file);
    }
}
