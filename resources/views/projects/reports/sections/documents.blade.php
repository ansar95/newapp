@php
    $monitor = $report->documents();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card label="Total Dokumen" :value="(string) $monitor->total()" />
        <x-stat-card label="Menunggu Client" :value="(string) $monitor->waitingClientCount()" />
        <x-stat-card label="Menunggu Admin" :value="(string) $monitor->waitingAdminCount()" />
        <x-stat-card label="Approved" :value="(string) $monitor->approvedCount()" />
        <x-stat-card label="Draft" :value="(string) $monitor->draftCount()" />
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Daftar Dokumen</h3>
        @if ($monitor->documents->isEmpty())
            <div class="p-2"><x-empty-state title="Belum ada dokumen project." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Dokumen</th>
                        <th class="px-4 py-2">Jenis</th>
                        <th class="px-4 py-2">Referensi</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Versi</th>
                        <th class="px-4 py-2 text-right">Revisi</th>
                        <th class="px-4 py-2">Status Sejak</th>
                        <th class="px-4 py-2">Durasi Siklus</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($monitor->documents as $document)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2">
                                <div class="font-medium text-maroon-950">{{ $document->document_title }}</div>
                                <div class="text-xs text-maroon-700">{{ $document->document_number }}</div>
                            </td>
                            <td class="px-4 py-2">{{ $document->documentType?->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $document->referenceLabel() ?? '-' }}</td>
                            <td class="px-4 py-2"><x-status-badge :status="$document->current_status" /></td>
                            <td class="px-4 py-2 text-right">{{ $document->current_version }}</td>
                            <td class="px-4 py-2 text-right">{{ $document->revisionCount() }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $document->statusSince() ? date_id($document->statusSince(), 'd M Y H:i') : '-' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ \App\Support\ProjectDocumentMonitor::formatDuration(\App\Support\ProjectDocumentMonitor::cycleSeconds($document, $monitor->now)) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
