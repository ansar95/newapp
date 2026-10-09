@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProjectDocument> $documents */
    $showReference ??= true;
    $canDownload = auth()->user()->hasPermission(\App\Enums\Permission::DocumentView)
        && auth()->user()->hasPermission(\App\Enums\Permission::DocumentDownload);
@endphp

@if ($documents->isEmpty())
    <div class="p-2">
        <x-empty-state title="Belum ada dokumen." description="Upload dokumen penagihan untuk dipantau status review client-nya." />
    </div>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-maroon-200 text-sm">
            <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
            <tr>
                <th class="min-w-56 px-4 py-3">Document</th>
                <th class="px-4 py-3">{{ $showReference ? 'Type / Reference' : 'Type' }}</th>
                <th class="px-4 py-3">Version</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Submitted Date</th>
                <th class="px-4 py-3">Last Update</th>
                <th class="px-4 py-3">Age / Waiting</th>
                <th class="px-4 py-3">Action</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-maroon-100">
            @foreach ($documents as $document)
                @php
                    $status = $document->current_status;
                    $waiting = $document->waitingSeconds($now);
                    $activeVersion = $document->activeVersion();
                    $submittedAt = $document->firstSubmittedAt();
                @endphp
                <tr class="{{ $status === \App\Enums\ProjectDocumentStatus::Cancelled ? 'text-maroon-400' : 'hover:bg-maroon-50' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('project-documents.show', $document) }}" class="font-medium text-maroon-950 hover:underline">{{ $document->document_title }}</a>
                        <span class="block text-xs text-maroon-700">{{ $document->document_number }}</span>
                    </td>
                    <td class="px-4 py-3">
                        {{ $document->documentType?->name }}
                        @if ($showReference && $document->referenceLabel())
                            <span class="block text-xs text-maroon-700">{{ $document->referenceLabel() }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $document->current_version > 0 ? 'V'.$document->current_version : '-' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$status" /></td>
                    <td class="px-4 py-3 whitespace-nowrap">@if ($submittedAt)<x-date-display :value="$submittedAt" />@else - @endif</td>
                    <td class="px-4 py-3 whitespace-nowrap">@if ($document->statusSince())<x-date-display :value="$document->statusSince()" />@else - @endif</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($status->isWaitingClient())
                            <span class="text-xs font-bold uppercase tracking-wide text-maroon-800">Waiting Client</span>
                            <span class="block text-xs text-maroon-700">{{ \App\Support\ProjectDocumentMonitor::formatDuration($waiting) }}</span>
                        @elseif ($status->isWaitingAdmin())
                            <span class="text-xs font-bold uppercase tracking-wide text-maroon-900">Waiting Admin</span>
                            <span class="block text-xs text-maroon-700">{{ \App\Support\ProjectDocumentMonitor::formatDuration($waiting) }}</span>
                        @elseif ($status === \App\Enums\ProjectDocumentStatus::Approved)
                            <span class="text-xs font-bold uppercase tracking-wide text-maroon-800">Completed</span>
                            <span class="block text-xs text-maroon-700">Siklus {{ \App\Support\ProjectDocumentMonitor::formatDuration(\App\Support\ProjectDocumentMonitor::cycleSeconds($document, $now)) }}</span>
                        @elseif ($status === \App\Enums\ProjectDocumentStatus::Draft)
                            <span class="text-xs text-maroon-700">Draft {{ \App\Support\ProjectDocumentMonitor::formatDuration($waiting) }}</span>
                        @else
                            -
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('project-documents.show', $document) }}" class="text-xs font-medium text-maroon-900 underline">Open</a>
                            @if ($canDownload && $activeVersion)
                                <a href="{{ route('project-document-versions.view', $activeVersion) }}" target="_blank" rel="noopener" class="text-xs font-medium text-maroon-900 underline">View</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
