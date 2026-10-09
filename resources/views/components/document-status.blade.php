@props(['monitor', 'project'])

@php
    /** @var \App\Support\ProjectDocumentMonitor $monitor */
    $needRevision = $monitor->waitingAdminCount();
    $longestWaiting = $monitor->longestWaitingClient();
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-maroon-200 bg-white p-4 shadow-sm']) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-bold uppercase tracking-wide text-maroon-700">Document Status</p>
        <a href="{{ route('projects.documents.index', $project) }}" class="text-xs font-medium text-maroon-900 underline">Lihat Dokumen</a>
    </div>
    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
        <div>
            <dt class="text-xs text-maroon-700">Total</dt>
            <dd class="text-lg font-bold tabular-nums text-maroon-950">{{ $monitor->total() }}</dd>
        </div>
        <div>
            <dt class="text-xs text-maroon-700">Waiting Client</dt>
            <dd class="text-lg font-bold tabular-nums text-maroon-950">{{ $monitor->waitingClientCount() }}</dd>
        </div>
        <div>
            <dt class="text-xs text-maroon-700">Need Revision</dt>
            <dd class="text-lg font-bold tabular-nums text-maroon-950">{{ $needRevision }}</dd>
        </div>
        <div>
            <dt class="text-xs text-maroon-700">Approved</dt>
            <dd class="text-lg font-bold tabular-nums text-maroon-950">{{ $monitor->approvedCount() }}</dd>
        </div>
    </dl>
    @if ($needRevision > 0)
        <p class="mt-3 rounded-md border border-maroon-300 bg-maroon-50 px-3 py-2 text-xs font-medium text-maroon-900" role="alert">
            {{ $needRevision }} dokumen membutuhkan revisi dari admin.
        </p>
    @elseif ($longestWaiting)
        <p class="mt-3 text-xs text-maroon-700">
            Menunggu client terlama: {{ $longestWaiting->document_number }} ({{ \App\Support\ProjectDocumentMonitor::formatDuration($longestWaiting->waitingSeconds($monitor->now)) }}).
        </p>
    @endif
</div>
