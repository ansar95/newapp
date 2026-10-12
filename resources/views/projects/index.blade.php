<x-app-layout>
    <x-slot name="header">Projects</x-slot>

    <x-page-header title="Projects" subtitle="Manage your projects">
        @can('create', \App\Models\Project::class)
            <a href="{{ route('projects.create') }}"
               class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                + New Project
            </a>
        @endcan
    </x-page-header>

    @can('import', \App\Models\Project::class)
        <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4 shadow-sm" x-data="{ submitting: false }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-maroon-950">Import Project</h3>
                    <p class="mt-1 text-xs text-maroon-700">
                        Upload file ZIP hasil Export Project. Semua data (BOQ, payment, pengadaan, penagihan, dokumen, man power, asset) dan lampirannya dipulihkan dengan status aslinya.
                        Import ditolak jika kode project atau nomor transaksi sudah ada.
                    </p>
                </div>
                <form method="POST" action="{{ route('projects.import') }}" enctype="multipart/form-data"
                      class="flex flex-col gap-2 sm:flex-row sm:items-center"
                      @submit="if (!confirm('Import project dari file ini?')) { $event.preventDefault(); return; } submitting = true">
                    @csrf
                    <input type="file" name="file" accept=".zip,application/zip" required
                           class="block w-full text-sm text-maroon-800 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white sm:w-auto">
                    <button type="submit"
                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Importing...' : 'Import Project'">
                        Import Project
                    </button>
                </form>
            </div>
            <x-input-error :messages="$errors->get('file')" class="mt-2" />
        </div>
    @endcan

    <form method="GET" action="{{ route('projects.index') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full max-w-xl">
            <x-input-label for="search" value="Search" />
            <x-text-input id="search" type="text" name="search" class="mt-1 block w-full" :value="$search"
                          placeholder="Cari code, nama, atau client..." />
        </div>
        <div class="w-full sm:w-48">
            <x-input-label for="status" value="Filter Status" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua status</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($status === $statusOption->value)>
                        {{ str_replace('_', ' ', $statusOption->value) }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
            Search
        </button>
    </form>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        @if ($projects->isEmpty())
            <div class="p-2">
                <x-empty-state
                    title="Belum ada project."
                    description="Buat project pertama untuk memulai workflow BOQ, payment, dan progress."
                    :action-label="auth()->user()->can('create', \App\Models\Project::class) ? 'Create Project' : null"
                    :action-url="auth()->user()->can('create', \App\Models\Project::class) ? route('projects.create') : null"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Client</th>
                        <th class="px-4 py-3">Nilai Kontrak</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Progress</th>
                        <th class="px-4 py-3">Target Date</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($projects as $project)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium text-maroon-950">{{ $project->code }}</td>
                            <td class="px-4 py-3">
                                {{ $project->name }}
                                @if ($project->contract_number)
                                    <span class="mt-0.5 block text-xs text-maroon-700">Kontrak: {{ $project->contract_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $project->client_name }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($project->contract_value !== null)
                                    <x-money :amount="$project->contract_value" />
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-status-badge :status="$project->status" /></td>
                            <td class="px-4 py-3 min-w-[140px]"><x-progress-bar :percent="$project->progressPercentage()" /></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-date-display :value="$project->target_end_date" />
                                <div class="mt-1"><x-contract-end-badge :project="$project" /></div>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-maroon-900 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600">View</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-maroon-100 px-4 py-3">{{ $projects->links() }}</div>
        @endif
    </div>
</x-app-layout>
