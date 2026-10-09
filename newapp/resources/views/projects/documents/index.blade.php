<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Monitoring Dokumen</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    @php
        /** @var \App\Support\ProjectDocumentMonitor $monitor */
        $longestWaiting = $monitor->longestWaitingClient();
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 rounded-xl border border-maroon-200 bg-white p-4">
        <div>
            <h3 class="text-base font-semibold text-maroon-950">Monitoring Dokumen</h3>
            <p class="mt-1 text-sm text-maroon-700">Pantau seluruh dokumen project dan status review client.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('project-documents.create', $project) }}"
               class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                + Upload Document
            </a>
        @endif
    </div>

    @unless (\App\Models\ProjectDocument::projectAcceptsDocuments($project))
        <p class="mb-4 rounded-lg border border-maroon-300 bg-maroon-50 px-4 py-3 text-sm font-medium text-maroon-900">
            Dokumen hanya dapat dikelola pada project berstatus ACTIVE atau COMPLETED.
        </p>
    @endunless

    <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card label="Total Dokumen" :value="$monitor->total()" hint="Tidak termasuk CANCELLED" />
        <x-stat-card label="Submitted" :value="$monitor->submittedCount()" hint="Termasuk resubmitted" />
        <x-stat-card label="Under Review" :value="$monitor->underReviewCount()" />
        <x-stat-card label="Revision" :value="$monitor->revisionCount()" />
        <x-stat-card label="Approved" :value="$monitor->approvedCount()" />
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-maroon-300 bg-maroon-50 px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-maroon-900">Waiting Admin</p>
            <p class="mt-1 text-sm text-maroon-900"><span class="text-lg font-bold tabular-nums">{{ $monitor->waitingAdminCount() }}</span> dokumen perlu direvisi</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-maroon-800">Waiting Client</p>
            <p class="mt-1 text-sm text-maroon-900"><span class="text-lg font-bold tabular-nums">{{ $monitor->waitingClientCount() }}</span> dokumen menunggu review client</p>
            @if ($longestWaiting)
                <p class="mt-1 text-xs text-maroon-700">
                    Terlama: {{ $longestWaiting->document_number }} ({{ \App\Support\ProjectDocumentMonitor::formatDuration($longestWaiting->waitingSeconds($monitor->now)) }})
                </p>
            @endif
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-maroon-800">Completed</p>
            <p class="mt-1 text-sm text-maroon-900"><span class="text-lg font-bold tabular-nums">{{ $monitor->completedCount() }}</span> dokumen approved</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="border-b border-maroon-100 px-4 py-3">
            <h3 class="text-base font-semibold text-maroon-950">Daftar Dokumen</h3>
            <p class="text-xs text-maroon-700">Umur dihitung dalam hari kalender dari riwayat status. Status dokumen tidak mengubah status penagihan.</p>
        </div>
        @include('project-documents._table', ['documents' => $monitor->documents, 'now' => $monitor->now])
    </div>
</x-app-layout>
