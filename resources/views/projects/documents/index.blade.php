<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Arsip Dokumen</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 rounded-xl border border-maroon-200 bg-white p-4">
        <div>
            <h3 class="text-base font-semibold text-maroon-950">Arsip Dokumen</h3>
            <p class="mt-1 text-sm text-maroon-700">Simpan berkas project. Tidak ada proses approval atau revisi.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('project-documents.create', $project) }}"
               class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                + Unggah Dokumen
            </a>
        @endif
    </div>

    @unless (\App\Models\ProjectDocument::projectAcceptsDocuments($project))
        <p class="mb-4 rounded-lg border border-maroon-300 bg-maroon-50 px-4 py-3 text-sm font-medium text-maroon-900">
            Dokumen hanya dapat diunggah pada project berstatus ACTIVE atau COMPLETED.
        </p>
    @endunless

    <div class="mb-4">
        <x-stat-card label="Total Dokumen" :value="$monitor->total()" hint="Berkas yang tersimpan" />
    </div>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="border-b border-maroon-100 px-4 py-3">
            <h3 class="text-base font-semibold text-maroon-950">Daftar Dokumen</h3>
        </div>
        @include('project-documents._table', ['documents' => $monitor->documents, 'now' => $monitor->now])
    </div>
</x-app-layout>
