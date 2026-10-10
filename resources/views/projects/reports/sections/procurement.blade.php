@php
    $procurement = $report->procurement();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($procurement['by_type'] as $typeSummary)
            <x-stat-card :label="$typeSummary['type']->label()" :value="rupiah($typeSummary['paid'])"
                         :hint="$typeSummary['count'].' permintaan · estimasi '.rupiah($typeSummary['estimated']).($typeSummary['type']->isAllocatable() ? ' · teralokasi '.rupiah($typeSummary['allocated']) : '')" />
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2 text-xs">
        @foreach (\App\Enums\ProcurementStatus::cases() as $status)
            <span class="rounded-md border border-maroon-200 bg-white px-2.5 py-1 font-semibold text-maroon-800">
                {{ $status->label() }}: {{ $procurement['by_status'][$status->value] }}
            </span>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Daftar Permintaan &amp; Pembelian</h3>
        @if ($procurement['requests']->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada permintaan pada periode/filter ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">No / Tanggal</th>
                        <th class="px-4 py-2">Jenis</th>
                        <th class="px-4 py-2">Supplier</th>
                        <th class="px-4 py-2">Barang</th>
                        <th class="px-4 py-2 text-right">Estimasi</th>
                        <th class="px-4 py-2 text-right">Dibayar</th>
                        <th class="px-4 py-2 text-right">Teralokasi</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Pemohon</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($procurement['requests'] as $procurementRequest)
                        <tr class="break-inside-avoid align-top">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <a href="{{ route('procurement-requests.show', $procurementRequest) }}" class="font-medium text-maroon-950 hover:underline">{{ $procurementRequest->request_number }}</a>
                                <div class="text-xs text-maroon-700"><x-date-display :value="$procurementRequest->request_date" /></div>
                            </td>
                            <td class="px-4 py-2">{{ $procurementRequest->type->label() }}</td>
                            <td class="px-4 py-2">{{ $procurementRequest->supplier ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <ul class="space-y-0.5">
                                    @foreach ($procurementRequest->items as $item)
                                        <li class="text-xs text-maroon-900">
                                            {{ $item->item_name }} — {{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}
                                            × {{ rupiah($item->paid_unit_price ?? $item->unit_price) }}
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$procurementRequest->estimated_total" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                @if ($procurementRequest->paid_total !== null)
                                    <x-money :amount="$procurementRequest->paid_total" />
                                    <div class="text-xs text-maroon-700"><x-date-display :value="$procurementRequest->payment_date" /></div>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                @if ($procurementRequest->type->isAllocatable() && $procurementRequest->isPaid())
                                    <x-money :amount="$procurementRequest->allocatedAmount()" />
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-2"><x-status-badge :status="$procurementRequest->status" /></td>
                            <td class="px-4 py-2">{{ $procurementRequest->creator?->name ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
