<x-app-layout>
    <x-slot name="header">Laporan Progress</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Progress', 'url' => null],
    ]" />

    <x-page-header title="Laporan Progress" subtitle="Progress keseluruhan project berdasarkan BOQ approved.">
        <a href="{{ route('reports.progress.export', request()->query()) }}"
           class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
            Export CSV
        </a>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card label="Projects" :value="(string) $summary['total']" />
        <x-stat-card label="Avg Progress" :value="$summary['avg_progress'].'%'" />
        <x-stat-card label="Di bawah 50%" :value="(string) $summary['below_50']" />
        <x-stat-card label="50–99%" :value="(string) $summary['between_50_99']" />
        <x-stat-card label="Selesai 100%" :value="(string) $summary['complete']" />
    </div>

    <form method="GET" action="{{ route('reports.progress') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full sm:w-64">
            <x-input-label for="project_id" value="Project" />
            <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) $filters['project_id'] === (string) $project->id)>
                        {{ $project->code }} — {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="w-full sm:w-48">
            <x-input-label for="status" value="Status Project" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>{{ $statusOption->value }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm">
        @if ($rows->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada data progress untuk filter ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Progress</th>
                        <th class="px-4 py-3">Earned Value</th>
                        <th class="px-4 py-3">Nilai BOQ</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('projects.progress.index', $row['project']) }}" class="text-maroon-950 hover:underline">{{ $row['code'] }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $row['name'] }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$row['status']" /></td>
                            <td class="px-4 py-3 min-w-[140px]">
                                <x-progress-bar :percent="$row['progress']" />
                                <p class="mt-1 text-xs text-maroon-700"><x-percent :value="$row['progress']" :decimals="1" /></p>
                            </td>
                            <td class="px-4 py-3"><x-money :amount="$row['earned_value']" /></td>
                            <td class="px-4 py-3"><x-money :amount="$row['boq_value']" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
