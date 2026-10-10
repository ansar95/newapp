@php
    $billing = $report->billing();
    $billingSummary = $billing['billingSummary'];
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Nilai Kontrak" :value="rupiah($billingSummary->contractValue())" />
        <x-stat-card label="Total Ditagih" :value="rupiah($billingSummary->totalBilled())"
                     :hint="percent_format($billingSummary->billedPercentage(), 1).' dari kontrak · periode ini '.rupiah($billing['summary']['billed_in_period'])" />
        <x-stat-card label="Diterima" :value="rupiah($billingSummary->totalReceived())"
                     :hint="'Periode ini '.rupiah($billing['summary']['received_in_period'])" />
        <x-stat-card label="Piutang" :value="rupiah($billingSummary->outstanding())"
                     :hint="$billing['summary']['overdue_count'].' tagihan lewat jatuh tempo ('.rupiah($billing['summary']['overdue']).')'" />
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Termin Pembayaran</h3>
        @if ($billing['terms']->isEmpty())
            <div class="p-2"><x-empty-state title="Belum ada termin." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Termin</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                        <th class="px-4 py-2 text-right">Ditagih</th>
                        <th class="px-4 py-2 text-right">Diterima</th>
                        <th class="px-4 py-2 text-right">Sisa Tagih</th>
                        <th class="px-4 py-2">Jatuh Tempo</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($billing['terms'] as $term)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2 font-medium text-maroon-950">{{ $term->displayName() }}</td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$term->amount" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$billingSummary->termBilled($term)" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$billingSummary->termReceived($term)" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$billingSummary->termRemaining($term)" /></td>
                            <td class="px-4 py-2 whitespace-nowrap"><x-date-display :value="$term->due_date" /></td>
                            <td class="px-4 py-2"><x-status-badge :status="$billingSummary->termStatus($term)" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Penagihan — {{ $report->periodLabel() }}</h3>
        @if ($billing['billings']->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada penagihan pada periode ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">No / Tanggal</th>
                        <th class="px-4 py-2">Termin</th>
                        <th class="px-4 py-2">Invoice</th>
                        <th class="px-4 py-2">Jatuh Tempo</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                        <th class="px-4 py-2 text-right">Diterima</th>
                        <th class="px-4 py-2 text-right">Sisa</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($billing['billings'] as $clientBilling)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <div class="font-medium text-maroon-950">{{ $clientBilling->billing_number }}</div>
                                <div class="text-xs text-maroon-700"><x-date-display :value="$clientBilling->billing_date" /></div>
                            </td>
                            <td class="px-4 py-2">{{ $clientBilling->term?->displayName() ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $clientBilling->invoice_number ?? '-' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap"><x-date-display :value="$clientBilling->due_date" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$clientBilling->billing_amount" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$clientBilling->receivedAmount()" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$clientBilling->outstandingAmount()" /></td>
                            <td class="px-4 py-2"><x-status-badge :status="$clientBilling->displayStatus()" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Pembayaran dari Client — {{ $report->periodLabel() }}</h3>
        @if ($billing['clientPayments']->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada pembayaran client pada periode ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">No Pembayaran</th>
                        <th class="px-4 py-2">Penagihan</th>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                        <th class="px-4 py-2">Referensi Bank</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($billing['clientPayments'] as $clientPayment)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2 font-medium text-maroon-950">{{ $clientPayment->payment_number }}</td>
                            <td class="px-4 py-2">{{ $clientPayment->billing?->billing_number }}</td>
                            <td class="px-4 py-2 whitespace-nowrap"><x-date-display :value="$clientPayment->payment_date" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$clientPayment->amount" /></td>
                            <td class="px-4 py-2">{{ $clientPayment->bank_reference ?? '-' }}</td>
                            <td class="px-4 py-2"><x-status-badge :status="$clientPayment->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
