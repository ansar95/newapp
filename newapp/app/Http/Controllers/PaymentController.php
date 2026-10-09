<?php

namespace App\Http\Controllers;

use App\Actions\Payment\ProcessPayment;
use App\Actions\Payment\VoidPayment;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Support\ProjectActualCost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(PaymentPlan $paymentPlan): View
    {
        $this->authorize('process', [Payment::class, $paymentPlan]);

        $paymentPlan->load(['boq.project', 'boq.cogs.items', 'items']);

        return view('payments.pay', [
            'paymentPlan' => $paymentPlan,
            'project' => $paymentPlan->boq->project,
            'boq' => $paymentPlan->boq,
            'actualCost' => ProjectActualCost::forProject($paymentPlan->boq->project),
        ]);
    }

    public function store(Request $request, PaymentPlan $paymentPlan, ProcessPayment $processPayment): RedirectResponse
    {
        $this->authorize('process', [Payment::class, $paymentPlan]);

        $data = $request->only(['payment_code', 'payment_date', 'amount', 'payment_method', 'reference_number', 'notes', 'overrun_reason']);

        if ($request->has('allocations')) {
            $data['allocations'] = collect($request->input('allocations', []))
                ->filter(fn ($row) => is_array($row) && ! empty($row['selected']))
                ->map(fn (array $row) => [
                    'boq_item_id' => $row['boq_item_id'] ?? null,
                    'allocated_amount' => $row['allocated_amount'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ])
                ->values()
                ->all();
        }

        $processPayment->handle($request->user(), $paymentPlan, $data);

        return redirect()
            ->route('projects.payment.index', $paymentPlan->boq->project)
            ->with('success', 'Payment berhasil diproses.');
    }

    public function void(Request $request, Payment $payment, VoidPayment $voidPayment): RedirectResponse
    {
        $this->authorize('void', $payment);

        $voidPayment->handle($request->user(), $payment, $request->only('void_reason'));

        return back()->with('success', 'Payment '.$payment->payment_code.' dibatalkan (VOID) dan tidak dihitung sebagai actual cost.');
    }
}
