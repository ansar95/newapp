<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Penagihan Client</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Penagihan', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    @php
        /** @var \App\Support\ProjectBillingSummary $summary */
        $hasContract = $summary->contractValue() > 0;
        $canViewPayments = auth()->user()->hasPermission(\App\Enums\Permission::BillingPaymentView);
        $billingsByTerm = $summary->billings->groupBy('project_term_id');
    @endphp

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">Penagihan Client — kelola termin, penagihan, dan pembayaran client.</p>
        <x-helper-text class="mt-1" text="Penagihan adalah pemasukan dari client, terpisah dari Payment (pengeluaran). Penagihan tidak mengubah BOQ, progress, BMHP, maupun Payment." />
    </div>

    <x-billing-summary :summary="$summary" :progress="$context->overallProgress" class="mb-6" />

    @unless (\App\Models\ClientBilling::projectAcceptsBilling($project))
        <p class="mb-4 rounded-lg border border-maroon-300 bg-maroon-50 px-4 py-3 text-sm font-medium text-maroon-900">
            Penagihan client hanya dapat diproses pada project berstatus ACTIVE atau COMPLETED.
        </p>
    @endunless

    {{-- Terms --}}
    <div class="mb-6 overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm"
         x-data="{ adding: {{ (string) old('term_form') === 'new' ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-maroon-100 px-4 py-3">
            <div>
                <h3 class="text-base font-semibold text-maroon-950">Termin Pembayaran</h3>
                <p class="text-xs text-maroon-700">Total termin tidak boleh melebihi nilai kontrak. Termin harus READY sebelum dapat ditagihkan.</p>
            </div>
            @if ($canManageTerms && $hasContract)
                <button type="button" @click="adding = !adding"
                        class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2"
                        x-text="adding ? 'Tutup' : '+ Add Termin'">+ Add Termin</button>
            @endif
        </div>

        @if (! $hasContract)
            <p class="border-b border-maroon-100 bg-maroon-50 px-4 py-3 text-sm font-medium text-maroon-900">
                Nilai kontrak project belum diisi. Isi nilai kontrak melalui Edit Project sebelum membuat termin.
            </p>
        @endif

        @if ($canManageTerms && $hasContract)
            <form method="POST" action="{{ route('project-terms.store', $project) }}" class="border-b border-maroon-200 bg-maroon-50 p-4"
                  x-show="adding" x-cloak>
                @csrf
                @include('projects.billing._term-form', ['term' => null])
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Simpan Termin</button>
                </div>
            </form>
        @endif

        @if ($summary->terms->isEmpty())
            <div class="p-2">
                <x-empty-state title="Belum ada termin." description="Buat termin pembayaran (persentase atau nilai tetap) sesuai kontrak dengan client." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="min-w-56 px-4 py-3">Termin</th>
                        <th class="px-4 py-3 text-right">%</th>
                        <th class="px-4 py-3 text-right">Nilai</th>
                        <th class="px-4 py-3 text-right">Billed</th>
                        <th class="px-4 py-3 text-right">Received</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3 text-right">Sisa Ditagih</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    @foreach ($summary->terms as $term)
                        @php
                            $termStatus = $summary->termStatus($term);
                            $termPercentage = $term->isPercentage()
                                ? (float) $term->percentage
                                : \App\Support\BmhpActualCost::percentage($summary->contractValue(), (float) $term->amount);
                            $canEditTerm = auth()->user()->can('update', $term);
                            $hasActiveBilling = $summary->termReserved($term) > 0;
                        @endphp
                        <tbody class="divide-y divide-maroon-100 border-b border-maroon-100"
                               x-data="{ editing: {{ (string) old('term_form') === (string) $term->id ? 'true' : 'false' }} }">
                        <tr class="{{ $term->isCancelled() ? 'text-maroon-400' : 'hover:bg-maroon-50' }}">
                            <td class="px-4 py-3">
                                <span class="font-medium text-maroon-950">{{ $term->displayName() }}</span>
                                <span class="block text-xs text-maroon-700">
                                    {{ $term->trigger_type->label() }}@if ($term->trigger_value): {{ $term->trigger_value }}@endif
                                    @if ($term->due_date) · Target <x-date-display :value="$term->due_date" /> @endif
                                    · {{ $billingsByTerm->get($term->id)?->count() ?? 0 }} penagihan
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums"><x-percent :value="$termPercentage" :decimals="2" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$term->amount" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->termBilled($term)" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->termReceived($term)" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->termOutstanding($term)" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$term->isCancelled() ? 0 : $summary->termRemaining($term)" /></td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$termStatus" /></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($canCreateBilling && $term->isReady() && $summary->termRemaining($term) > 0)
                                        <a href="{{ route('client-billings.create', [$project, 'term' => $term->id]) }}"
                                           class="rounded-md bg-maroon-800 px-2.5 py-1 text-xs font-semibold text-white hover:bg-maroon-900">Tagih</a>
                                    @endif
                                    @if ($canEditTerm)
                                        @if (! $term->isReady())
                                            <form method="POST" action="{{ route('project-terms.readiness', $term) }}">
                                                @csrf
                                                <input type="hidden" name="ready" value="1">
                                                <button type="submit" class="rounded-md border border-maroon-300 px-2.5 py-1 text-xs font-medium text-maroon-900 hover:bg-maroon-50">Set Ready</button>
                                            </form>
                                        @elseif (! $hasActiveBilling)
                                            <form method="POST" action="{{ route('project-terms.readiness', $term) }}">
                                                @csrf
                                                <input type="hidden" name="ready" value="0">
                                                <button type="submit" class="rounded-md border border-maroon-300 px-2.5 py-1 text-xs font-medium text-maroon-900 hover:bg-maroon-50">Set Not Ready</button>
                                            </form>
                                        @endif
                                        <button type="button" @click="editing = !editing"
                                                class="rounded-md border border-maroon-300 px-2.5 py-1 text-xs font-medium text-maroon-900 hover:bg-maroon-50">Edit</button>
                                        @unless ($hasActiveBilling)
                                            <form method="POST" action="{{ route('project-terms.destroy', $term) }}"
                                                  onsubmit="return confirm('Hapus termin ini? Termin yang memiliki riwayat penagihan akan dibatalkan, bukan dihapus.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-md border border-maroon-300 px-2.5 py-1 text-xs font-medium text-maroon-900 hover:bg-maroon-50">Hapus</button>
                                            </form>
                                        @endunless
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($canEditTerm)
                            <tr x-show="editing" x-cloak>
                                <td colspan="9" class="bg-maroon-50 p-4">
                                    <form method="POST" action="{{ route('project-terms.update', $term) }}">
                                        @csrf
                                        @method('PUT')
                                        @include('projects.billing._term-form', ['term' => $term])
                                        <div class="mt-4 flex justify-end gap-2">
                                            <button type="button" @click="editing = false" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Batal</button>
                                            <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Simpan Termin</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                        </tbody>
                    @endforeach
                    <tfoot class="bg-maroon-50 text-sm font-semibold text-maroon-950">
                    <tr>
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-right tabular-nums"><x-percent :value="$summary->totalTermPercentage()" :decimals="2" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->totalTermAmount()" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->totalBilled()" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->totalReceived()" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$summary->outstanding()" /></td>
                        <td colspan="3"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

    {{-- Billing history --}}
    <div class="mb-6 overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-maroon-100 px-4 py-3">
            <div>
                <h3 class="text-base font-semibold text-maroon-950">Riwayat Penagihan</h3>
                <p class="text-xs text-maroon-700">Urut kronologis. Penagihan yang dibatalkan tetap tercatat namun tidak dihitung.</p>
            </div>
            @if ($canCreateBilling && $summary->activeTerms()->contains(fn ($term) => $term->isReady()))
                <a href="{{ route('client-billings.create', $project) }}"
                   class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    + New Billing
                </a>
            @endif
        </div>

        @if ($summary->billings->isEmpty())
            <div class="p-2">
                <x-empty-state title="Belum ada penagihan." description="Tandai termin sebagai READY lalu buat penagihan ke client." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">No Penagihan</th>
                        <th class="px-4 py-3">Termin</th>
                        <th class="px-4 py-3 text-right">Nilai</th>
                        <th class="px-4 py-3 text-right">Received</th>
                        <th class="px-4 py-3 text-right">Outstanding</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($summary->billings as $billing)
                        <tr class="{{ $billing->isCancelled() ? 'text-maroon-400' : 'hover:bg-maroon-50' }}">
                            <td class="px-4 py-3 text-maroon-700">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$billing->billing_date" /></td>
                            <td class="px-4 py-3 font-medium text-maroon-950">
                                {{ $billing->billing_number }}
                                @if ($billing->invoice_number)
                                    <span class="block text-xs font-normal text-maroon-700">Invoice {{ $billing->invoice_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $billing->term?->displayName() }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$billing->billing_amount" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$billing->receivedAmount()" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$billing->outstandingAmount()" /></td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$billing->displayStatus()" /></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('client-billings.show', $billing) }}" class="font-medium text-maroon-900 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600">Open</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Client payment history --}}
    @if ($canViewPayments)
        @php
            $payments = $summary->billings
                ->flatMap(fn ($billing) => $billing->payments->each(fn ($payment) => $payment->setRelation('billing', $billing)))
                ->sortBy([['payment_date', 'asc'], ['id', 'asc']])
                ->values();
        @endphp
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
            <div class="border-b border-maroon-100 px-4 py-3">
                <h3 class="text-base font-semibold text-maroon-950">Riwayat Pembayaran Client</h3>
                <p class="text-xs text-maroon-700">Pembayaran yang dibatalkan tetap tercatat namun tidak dihitung sebagai penerimaan.</p>
            </div>
            @if ($payments->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-maroon-700">Belum ada pembayaran dari client.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-maroon-200 text-sm">
                        <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">No Pembayaran</th>
                            <th class="px-4 py-3">Penagihan</th>
                            <th class="px-4 py-3">Referensi Bank</th>
                            <th class="px-4 py-3 text-right">Nilai</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-maroon-100">
                        @foreach ($payments as $payment)
                            <tr class="{{ $payment->isReceived() ? 'hover:bg-maroon-50' : 'text-maroon-400' }}">
                                <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$payment->payment_date" /></td>
                                <td class="px-4 py-3 font-medium text-maroon-950">{{ $payment->payment_number }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('client-billings.show', $payment->billing) }}" class="text-maroon-900 hover:underline">{{ $payment->billing->billing_number }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $payment->bank_reference ?? '-' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$payment->amount" /></td>
                                <td class="px-4 py-3"><x-status-badge :status="$payment->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
