@php
    $payment = $report->payment();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Payment Plan" :value="rupiah($payment['summary']['planned'])" :hint="$payment['summary']['plans'].' plan'" />
        <x-stat-card label="Dibayar (Total)" :value="rupiah($payment['summary']['paid_all_time'])" />
        <x-stat-card label="Dibayar (Periode)" :value="rupiah($payment['summary']['paid_in_period'])"
                     :hint="$payment['summary']['payments'].' pembayaran · '.$payment['summary']['voided'].' void'" />
        <x-stat-card label="Sisa Plan" :value="rupiah(max(0, $payment['summary']['planned'] - $payment['summary']['paid_all_time']))" />
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Payment Plan</h3>
        @if ($payment['plans']->isEmpty())
            <div class="p-2"><x-empty-state title="Belum ada payment plan." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Referensi</th>
                        <th class="px-4 py-2">BOQ</th>
                        <th class="px-4 py-2">Jenis</th>
                        <th class="px-4 py-2">Rencana Bayar</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                        <th class="px-4 py-2 text-right">Dibayar</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($payment['plans'] as $plan)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2 font-medium text-maroon-950">{{ $plan->reference_code }}</td>
                            <td class="px-4 py-2">{{ $plan->boq?->code }}</td>
                            <td class="px-4 py-2">{{ $plan->type?->label() }}</td>
                            <td class="px-4 py-2 whitespace-nowrap"><x-date-display :value="$plan->planned_date" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$plan->amount" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$plan->paidAmount()" /></td>
                            <td class="px-4 py-2"><x-status-badge :status="$plan->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Riwayat Pembayaran — {{ $report->periodLabel() }}</h3>
        @if ($payment['payments']->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada pembayaran pada periode ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Kode</th>
                        <th class="px-4 py-2">Payment Plan</th>
                        <th class="px-4 py-2">Tanggal</th>
                        <th class="px-4 py-2 text-right">Nilai</th>
                        <th class="px-4 py-2">Metode / Referensi</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($payment['payments'] as $paymentRow)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2 font-medium text-maroon-950">{{ $paymentRow->payment_code }}</td>
                            <td class="px-4 py-2">{{ $paymentRow->paymentPlan?->reference_code }}</td>
                            <td class="px-4 py-2 whitespace-nowrap"><x-date-display :value="$paymentRow->payment_date" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$paymentRow->amount" /></td>
                            <td class="px-4 py-2">{{ $paymentRow->payment_method ?? '-' }}{{ $paymentRow->reference_number ? ' · '.$paymentRow->reference_number : '' }}</td>
                            <td class="px-4 py-2">
                                <x-status-badge :status="$paymentRow->status" />
                                @if ($paymentRow->void_reason)
                                    <div class="mt-1 text-xs text-maroon-700">{{ $paymentRow->void_reason }}</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
