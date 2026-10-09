<?php

namespace App\Http\Controllers;

use App\Actions\Billing\CancelClientBilling;
use App\Actions\Billing\CreateClientBilling;
use App\Actions\Billing\ReturnClientBillingToDraft;
use App\Actions\Billing\SendClientBilling;
use App\Actions\Billing\SubmitClientBilling;
use App\Actions\Billing\UpdateClientBilling;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Support\ProjectBillingSummary;
use App\Support\ProjectDocumentMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientBillingController extends Controller
{
    private const FIELDS = ['billing_date', 'billing_amount', 'due_date', 'description'];

    public function create(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ClientBilling::class, $project]);

        return view('client-billings.create', [
            'project' => $project,
            'billing' => null,
            'termOptions' => $this->termOptions(ProjectBillingSummary::forProject($project)),
            'selectedTermId' => $request->integer('term') ?: null,
        ]);
    }

    public function store(Request $request, Project $project, CreateClientBilling $createClientBilling): RedirectResponse
    {
        $this->authorize('view', $project);
        $this->authorize('create', [ClientBilling::class, $project]);

        $billing = $createClientBilling->handle(
            $request->user(),
            $project,
            $request->only(['project_term_id', ...self::FIELDS]),
            $request->file('attachment')
        );

        return redirect()
            ->route('client-billings.show', $billing)
            ->with('success', 'Penagihan '.$billing->billing_number.' dibuat sebagai DRAFT.');
    }

    public function show(Request $request, ClientBilling $clientBilling): View
    {
        $this->authorize('view', $clientBilling);

        $clientBilling->load(['project', 'term', 'creator', 'submitter', 'sender', 'canceller', 'payments.creator', 'payments.canceller']);
        $summary = ProjectBillingSummary::forProject($clientBilling->project);
        $term = $summary->terms->firstWhere('id', $clientBilling->project_term_id);

        return view('client-billings.show', [
            'billing' => $clientBilling,
            'project' => $clientBilling->project,
            'term' => $term,
            'summary' => $summary,
            'canViewPayments' => $request->user()->can('viewPayments', $clientBilling),
            'documentMonitor' => $request->user()->can('viewForProject', [ProjectDocument::class, $clientBilling->project])
                ? ProjectDocumentMonitor::forBilling($clientBilling)
                : null,
            'canCreateDocument' => $request->user()->can('create', [ProjectDocument::class, $clientBilling->project]),
        ]);
    }

    public function edit(ClientBilling $clientBilling): View
    {
        $this->authorize('update', $clientBilling);

        $clientBilling->load(['project', 'term']);

        return view('client-billings.edit', [
            'project' => $clientBilling->project,
            'billing' => $clientBilling,
            'termOptions' => $this->termOptions(ProjectBillingSummary::forProject($clientBilling->project), $clientBilling),
            'selectedTermId' => $clientBilling->project_term_id,
        ]);
    }

    public function update(Request $request, ClientBilling $clientBilling, UpdateClientBilling $updateClientBilling): RedirectResponse
    {
        $this->authorize('update', $clientBilling);

        $updateClientBilling->handle($request->user(), $clientBilling, $request->only(self::FIELDS), $request->file('attachment'));

        return redirect()
            ->route('client-billings.show', $clientBilling)
            ->with('success', 'Penagihan berhasil diperbarui.');
    }

    public function submit(Request $request, ClientBilling $clientBilling, SubmitClientBilling $submitClientBilling): RedirectResponse
    {
        $this->authorize('submit', $clientBilling);

        $submitClientBilling->handle($request->user(), $clientBilling);

        return back()->with('success', 'Penagihan disubmit dan siap dikirim ke client.');
    }

    public function returnToDraft(Request $request, ClientBilling $clientBilling, ReturnClientBillingToDraft $returnClientBillingToDraft): RedirectResponse
    {
        $this->authorize('returnToDraft', $clientBilling);

        $returnClientBillingToDraft->handle($request->user(), $clientBilling);

        return back()->with('success', 'Penagihan dikembalikan ke DRAFT.');
    }

    public function send(Request $request, ClientBilling $clientBilling, SendClientBilling $sendClientBilling): RedirectResponse
    {
        $this->authorize('send', $clientBilling);

        $sendClientBilling->handle($request->user(), $clientBilling, $request->only(['invoice_number', 'invoice_date', 'due_date']));

        return back()->with('success', 'Penagihan ditandai SENT dan dihitung sebagai tagihan ke client.');
    }

    public function cancel(Request $request, ClientBilling $clientBilling, CancelClientBilling $cancelClientBilling): RedirectResponse
    {
        $this->authorize('cancel', $clientBilling);

        $cancelClientBilling->handle($request->user(), $clientBilling, $request->only('cancel_reason'));

        return back()->with('success', 'Penagihan dibatalkan dan tidak lagi dihitung.');
    }

    public function attachment(ClientBilling $clientBilling): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $clientBilling);

        if (! $clientBilling->attachment || ! Storage::disk('public')->exists($clientBilling->attachment)) {
            return back()->with('error', 'File lampiran penagihan tidak ditemukan.');
        }

        return Storage::disk('public')->download($clientBilling->attachment);
    }

    /**
     * Terms that can receive a billing, with the amount still available. When editing, the billing's own amount
     * is given back to its term.
     *
     * @return list<array{id: int, label: string, amount: float, reserved: float, remaining: float}>
     */
    private function termOptions(ProjectBillingSummary $summary, ?ClientBilling $editing = null): array
    {
        return $summary->activeTerms()
            ->filter(fn ($term) => $editing !== null ? $term->id === $editing->project_term_id : $term->isReady())
            ->map(function ($term) use ($summary, $editing): array {
                $reserved = $summary->termReserved($term);

                if ($editing !== null) {
                    $reserved = round($reserved - (float) $editing->billing_amount, 2);
                }

                return [
                    'id' => $term->id,
                    'label' => $term->displayName(),
                    'amount' => (float) $term->amount,
                    'reserved' => $reserved,
                    'remaining' => round(max(0, (float) $term->amount - $reserved), 2),
                ];
            })
            ->values()
            ->all();
    }
}
