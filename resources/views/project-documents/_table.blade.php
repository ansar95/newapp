@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProjectDocument> $documents */
    $showReference ??= true;
    $canDownload = auth()->user()->hasPermission(\App\Enums\Permission::DocumentView)
        && auth()->user()->hasPermission(\App\Enums\Permission::DocumentDownload);
@endphp

@if ($documents->isEmpty())
    <div class="p-2">
        <x-empty-state title="Belum ada dokumen." description="Unggah berkas untuk menyimpannya di arsip project." />
    </div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-maroon-200 text-sm">
            <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
            <tr>
                <th class="min-w-56 px-4 py-3">Dokumen</th>
                <th class="px-4 py-3">{{ $showReference ? 'Jenis / Referensi' : 'Jenis' }}</th>
                <th class="px-4 py-3">Berkas</th>
                <th class="px-4 py-3">Diunggah</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-maroon-100">
            @foreach ($documents as $document)
                @php
                    $activeVersion = $document->activeVersion();
                    $uploadedAt = $activeVersion?->uploaded_at ?? $document->created_at;
                @endphp
                <tr class="hover:bg-maroon-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('project-documents.show', $document) }}" class="font-medium text-maroon-950 hover:underline">{{ $document->document_title }}</a>
                        <span class="block text-xs text-maroon-700">{{ $document->document_number }}</span>
                    </td>
                    <td class="px-4 py-3">
                        {{ $document->typeName() }}
                        @if ($showReference && $document->referenceLabel())
                            <span class="block text-xs text-maroon-700">{{ $document->referenceLabel() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="block max-w-xs truncate">{{ $activeVersion?->original_filename ?? '-' }}</span>
                        @if ($activeVersion)
                            <span class="text-xs text-maroon-700">{{ $activeVersion->readableSize() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        <x-date-display :value="$uploadedAt" />
                        @if ($activeVersion?->uploader)
                            <span class="block text-xs text-maroon-700">{{ $activeVersion->uploader->name }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('project-documents.show', $document) }}" class="text-xs font-medium text-maroon-900 underline">Buka</a>
                            @if ($canDownload && $activeVersion)
                                <a href="{{ route('project-document-versions.view', $activeVersion) }}" target="_blank" rel="noopener" class="text-xs font-medium text-maroon-900 underline">Lihat</a>
                                <a href="{{ route('project-document-versions.download', $activeVersion) }}" class="text-xs font-medium text-maroon-900 underline">Unduh</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
