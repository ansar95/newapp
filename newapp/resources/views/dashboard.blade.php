<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <x-page-header
        title="Dashboard"
        subtitle="Kelola project berdasarkan BOQ: penyusunan biaya, approval, pembayaran, sampai monitoring progress pekerjaan."
    >
        <div class="flex flex-wrap gap-2">
            @if (auth()->user()->hasPermission(\App\Enums\Permission::ReportView))
                <a href="{{ route('reports.index') }}"
                   class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
                    Reports
                </a>
            @endif
            @can('create', \App\Models\Project::class)
                <a href="{{ route('projects.create') }}"
                   class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
                    + New Project
                </a>
            @endcan
        </div>
    </x-page-header>

    @if ($contractEndingProjects->isNotEmpty())
        <div role="alert" class="mb-6 rounded-xl border border-maroon-400 bg-white shadow-sm">
            <div class="flex items-center gap-3 rounded-t-xl bg-maroon-800 px-5 py-3 text-white">
                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-maroon-800">!</span>
                <div>
                    <h2 class="text-sm font-bold">Peringatan Kontrak</h2>
                    <p class="text-xs text-white/90">
                        {{ $contractEndingProjects->count() }} project dengan kontrak berakhir dalam {{ \App\Models\Project::CONTRACT_ENDING_ALERT_DAYS }} hari atau sudah lewat.
                    </p>
                </div>
            </div>
            <ul class="divide-y divide-maroon-100">
                @foreach ($contractEndingProjects as $project)
                    <li>
                        <a href="{{ route('projects.show', $project) }}"
                           class="flex flex-col gap-2 px-5 py-3 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-maroon-950">{{ $project->code }} — {{ $project->name }}</p>
                                <p class="truncate text-xs text-maroon-700">
                                    {{ $project->client_name }}
                                    @if ($project->contract_number)
                                        · Kontrak {{ $project->contract_number }}
                                    @endif
                                    · Berakhir <x-date-display :value="$project->target_end_date" />
                                </p>
                            </div>
                            <x-contract-end-badge :project="$project" class="shrink-0 self-start sm:self-auto" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Total Projects" :value="(string) $totalProjects" />
        <x-stat-card label="Active Projects" :value="(string) $activeProjects" />
        <x-stat-card label="Completed Projects" :value="(string) $completedProjects" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-maroon-200/80 bg-white p-5 shadow-sm shadow-maroon-100/40">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-maroon-950">Recent Projects</h2>
                <a href="{{ route('projects.index') }}" class="text-sm font-medium text-maroon-700 hover:underline">Lihat semua</a>
            </div>
            @forelse ($recentProjects as $project)
                <a href="{{ route('projects.show', $project) }}"
                   class="flex items-center justify-between gap-3 border-b border-maroon-100 py-3 last:border-0 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-maroon-950">{{ $project->code }} — {{ $project->name }}</p>
                        <p class="truncate text-xs text-maroon-700">{{ $project->client_name }}</p>
                    </div>
                    <x-status-badge :status="$project->status" />
                </a>
            @empty
                <x-empty-state
                    title="Belum ada project."
                    :action-label="auth()->user()->can('create', \App\Models\Project::class) ? 'Create Project' : null"
                    :action-url="auth()->user()->can('create', \App\Models\Project::class) ? route('projects.create') : null"
                />
            @endforelse
        </div>

        <div class="rounded-2xl border border-maroon-200/80 bg-white p-5 shadow-sm shadow-maroon-100/40">
            <h2 class="mb-4 text-base font-semibold text-maroon-950">Project Progress</h2>
            @forelse ($recentProjects as $project)
                <div class="mb-4 last:mb-0">
                    <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                        <span class="truncate font-medium text-maroon-900">{{ $project->code }}</span>
                        <span class="shrink-0 text-xs text-maroon-700"><x-percent :value="$project->progressPercentage()" :decimals="1" /></span>
                    </div>
                    <x-progress-bar :percent="$project->progressPercentage()" :show-label="false" />
                </div>
            @empty
                <p class="text-sm text-maroon-700">Belum ada data progress.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
