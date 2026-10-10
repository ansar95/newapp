@php
    $progress = $report->progress();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Progress Project" :value="percent_format($progress['summary']['progress'], 1)" />
        <x-stat-card label="Earned Value" :value="rupiah($progress['summary']['earned_value'])"
                     :hint="'Dari nilai BOQ '.rupiah($progress['summary']['boq_value'])" />
        <x-stat-card label="Item Pekerjaan" :value="(string) $progress['summary']['items']"
                     :hint="$progress['summary']['completed'].' selesai · '.$progress['summary']['in_progress'].' berjalan · '.$progress['summary']['not_started'].' belum mulai'" />
        <x-stat-card label="Item Selesai" :value="(string) $progress['summary']['completed']" />
    </div>

    @forelse ($progress['boqs'] as $boq)
        <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-maroon-100 px-4 py-3">
                <h3 class="text-base font-semibold text-maroon-950">BOQ {{ $boq->code }}</h3>
                <p class="text-sm text-maroon-700">Progress BOQ: <x-percent :value="$boq->progressPercentage()" :decimals="1" class="font-semibold text-maroon-950" /></p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Item</th>
                        <th class="px-4 py-2 text-right">Volume</th>
                        <th class="px-4 py-2 text-right">Nilai BOQ</th>
                        <th class="px-4 py-2 text-right">Bobot</th>
                        <th class="px-4 py-2 text-right">Progress</th>
                        <th class="px-4 py-2 text-right">Nilai Selesai</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($boq->cogs->sortBy('sort_order') as $cogs)
                        <tr class="bg-maroon-50/60">
                            <td class="px-4 py-2 font-semibold text-maroon-950" colspan="4">{{ $cogs->code }} — {{ $cogs->name }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-maroon-950"><x-percent :value="$cogs->progressPercentage()" :decimals="1" /></td>
                            <td class="px-4 py-2" colspan="2"></td>
                        </tr>
                        @foreach ($cogs->items->sortBy('sort_order') as $item)
                            <tr class="break-inside-avoid">
                                <td class="px-4 py-2">
                                    <div class="font-medium text-maroon-950">{{ $item->name }}</div>
                                    <div class="text-xs text-maroon-700">{{ $item->code }}</div>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$item->total_price" /></td>
                                <td class="px-4 py-2 text-right"><x-percent :value="$item->weightAgainstBoq()" /></td>
                                <td class="px-4 py-2 text-right"><x-percent :value="$item->progress_percentage" :decimals="1" /></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="(float) $item->total_price * (float) $item->progress_percentage / 100" /></td>
                                <td class="px-4 py-2"><x-status-badge :status="$item->progressStatus()" /></td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <x-empty-state title="Belum ada BOQ approved." description="Laporan progress tersedia setelah BOQ project disetujui." />
    @endforelse
</div>
