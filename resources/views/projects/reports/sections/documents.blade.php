@php
    $monitor = $report->documents();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total Dokumen" :value="(string) $monitor->total()" hint="Berkas yang tersimpan" />
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
                        <th class="px-4 py-2">Berkas</th>
                        <th class="px-4 py-2">Diunggah</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($monitor->documents as $document)
                        @php($version = $document->activeVersion())
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2">
                                <div class="font-medium text-maroon-950">{{ $document->document_title }}</div>
                                <div class="text-xs text-maroon-700">{{ $document->document_number }}</div>
                            </td>
                            <td class="px-4 py-2">{{ $document->typeName() }}</td>
                            <td class="px-4 py-2">{{ $document->referenceLabel() ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $version?->original_filename ?? '-' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap">{{ ($version?->uploaded_at ?? $document->created_at) ? date_id($version?->uploaded_at ?? $document->created_at) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
