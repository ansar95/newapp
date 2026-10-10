@php
    $assets = $report->assets();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Penggunaan Aset" :value="(string) $assets['summary']['total']" hint="Penugasan aset aktif pada periode" />
        <x-stat-card label="Masih Digunakan" :value="(string) $assets['summary']['in_use']" />
        <x-stat-card label="Sudah Dikembalikan" :value="(string) $assets['summary']['released']" />
        <x-stat-card label="Pembelian Aset" :value="rupiah($assets['summary']['purchased_value'])"
                     :hint="$assets['summary']['purchased'].' unit dibayar pada periode'" />
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Aset di Project — {{ $report->periodLabel() }}</h3>
        @if ($assets['assignments']->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada aset pada periode ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Aset</th>
                        <th class="px-4 py-2">Kategori</th>
                        <th class="px-4 py-2">Sumber</th>
                        <th class="px-4 py-2">Digunakan Sejak</th>
                        <th class="px-4 py-2">Dikembalikan</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($assets['assignments'] as $assignment)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2">
                                <div class="font-medium text-maroon-950">{{ $assignment->asset?->name }}</div>
                                <div class="text-xs text-maroon-700">{{ $assignment->asset?->code }}</div>
                            </td>
                            <td class="px-4 py-2">{{ $assignment->asset?->category ?? '-' }}</td>
                            <td class="px-4 py-2">
                                {{ $assignment->asset?->source_type?->label() ?? '-' }}
                                @if ($assignment->asset?->procurementRequestItem?->request)
                                    <div class="text-xs text-maroon-700">{{ $assignment->asset->procurementRequestItem->request->request_number }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-2 whitespace-nowrap"><x-date-display :value="$assignment->assigned_at" /></td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $assignment->released_at ? date_id($assignment->released_at) : '-' }}</td>
                            <td class="px-4 py-2"><x-status-badge :status="$assignment->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
