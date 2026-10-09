@props(['monitor', 'project'])

@php
    /** @var \App\Support\ProjectDocumentMonitor $monitor */
    $needRevision = $monitor->waitingAdminCount();
    $waitingClient = $monitor->waitingClientCount();
@endphp

@if ($needRevision > 0 || $waitingClient > 0)
    <div {{ $attributes->merge(['class' => 'rounded-xl border border-maroon-300 bg-white p-4 shadow-sm']) }} role="region" aria-label="Document Action">
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Dokumen</p>
        @if ($needRevision > 0)
            <p class="mt-1 text-sm font-medium text-maroon-950">{{ $needRevision }} dokumen membutuhkan revisi.</p>
        @endif
        @if ($waitingClient > 0)
            <p class="mt-1 text-sm text-maroon-800">{{ $waitingClient }} dokumen sedang menunggu review client.</p>
        @endif
        <a href="{{ route('projects.documents.index', $project) }}"
           class="mt-3 inline-flex rounded-md border border-maroon-300 px-3 py-1.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-50">Review Documents</a>
    </div>
@endif
