<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CancelClientPayment;
use App\Actions\Billing\RecordClientPayment;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientPaymentController extends Controller
{
    public function store(Request $request, ClientBilling $clientBilling, RecordClientPayment $recordClientPayment): RedirectResponse
    {
        $this->authorize('recordPayment', $clientBilling);

        $payment = $recordClientPayment->handle(
            $request->user(),
            $clientBilling,
            $request->only(['payment_date', 'amount', 'payment_number', 'bank_reference', 'notes']),
            $request->file('proof_file')
        );

        return redirect()
            ->route('client-billings.show', $clientBilling)
            ->with('success', 'Pembayaran client '.$payment->payment_number.' dicatat.');
    }

    public function cancel(Request $request, ClientPayment $clientPayment, CancelClientPayment $cancelClientPayment): RedirectResponse
    {
        $this->authorize('cancel', $clientPayment);

        $cancelClientPayment->handle($request->user(), $clientPayment, $request->only('cancel_reason'));

        return back()->with('success', 'Pembayaran client dibatalkan dan tidak lagi dihitung sebagai penerimaan.');
    }

    public function proof(ClientPayment $clientPayment): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $clientPayment);

        if (! $clientPayment->proof_file || ! Storage::disk('public')->exists($clientPayment->proof_file)) {
            return back()->with('error', 'File bukti pembayaran tidak ditemukan.');
        }

        return Storage::disk('public')->download($clientPayment->proof_file);
    }
}
