@props(['project', 'tab' => 'overview', 'context' => null])

@php
    $progress = $context?->overallProgress ?? $project->progressPercentage();
@endphp

<x-contract-end-alert :project="$project" class="mb-4" />

<div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Project</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-tight text-maroon-950">{{ $project->code }}</h2>
            <p class="mt-1 text-lg text-maroon-800">{{ $project->name }}</p>
            <p class="mt-3 text-sm text-maroon-700">
                Client: <span class="font-medium text-maroon-900">{{ $project->client_name }}</span>
                @if ($project->contract_number)
                    <span class="mx-2 text-maroon-300">|</span>
                    Nomor Kontrak: <span class="font-medium text-maroon-900">{{ $project->contract_number }}</span>
                @endif
                @if ($project->contract_value !== null)
                    <span class="mx-2 text-maroon-300">|</span>
                    Nilai Kontrak: <x-money :amount="$project->contract_value" class="font-medium text-maroon-900" />
                @endif
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="text-sm text-maroon-700">Status:</span>
                <x-status-badge :status="$project->status" />
            </div>
        </div>
        <div class="flex w-full max-w-sm flex-col gap-3">
            <div>
                <p class="mb-2 text-sm font-medium text-maroon-700">Progress: <x-percent :value="$progress" :decimals="1" /></p>
                <x-progress-bar :percent="$progress" />
            </div>
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}"
                   class="inline-flex items-center justify-center rounded-md border border-maroon-300 px-4 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600">
                    Edit Project
                </a>
            @endcan
        </div>
    </div>

    <nav class="mt-5 flex flex-wrap gap-2 border-t border-maroon-100 pt-4" aria-label="Project sections">
        <a href="{{ route('projects.show', $project) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'overview' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
            Overview
        </a>
        <a href="{{ route('projects.boq.index', $project) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'boq' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
            BOQ
        </a>
        <a href="{{ route('projects.assets.index', $project) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'assets' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
            Assets
        </a>
        @can('viewForProject', [\App\Models\ProcurementRequest::class, $project])
            <a href="{{ route('projects.procurement.index', $project) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'procurement' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
                Pengadaan
            </a>
            <a href="{{ route('projects.bmhp.index', $project) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'bmhp' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
                BMHP
            </a>
            <a href="{{ route('projects.rentals.index', $project) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'rentals' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
                Sewa Alat
            </a>
        @endcan
        @can('viewForProject', [\App\Models\ClientBilling::class, $project])
            <a href="{{ route('projects.billing.index', $project) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'billing' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
                Penagihan
            </a>
        @endcan
        @can('viewForProject', [\App\Models\ProjectDocument::class, $project])
            <a href="{{ route('projects.documents.index', $project) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'documents' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
                Dokumen
            </a>
        @endcan
        <a href="{{ route('projects.payment.index', $project) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'payment' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
            Payment
        </a>
        <a href="{{ route('projects.progress.index', $project) }}"
           class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'progress' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
            Progress
        </a>
        @can('viewReports', $project)
            <a href="{{ route('projects.reports.index', $project) }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-maroon-600 {{ $tab === 'reports' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' }}">
                Laporan
            </a>
        @endcan
    </nav>
</div>
