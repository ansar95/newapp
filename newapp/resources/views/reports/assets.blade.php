<x-app-layout>
    <x-slot name="header">Laporan Assets</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Assets', 'url' => null],
    ]" />

    <x-page-header title="Laporan Assets" subtitle="Status master asset, kondisi, dan pemakaian project.">
        <a href="{{ route('reports.assets.export', request()->query()) }}"
           class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
            Export CSV
        </a>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-stat-card label="Total" :value="(string) $summary['total']" />
        <x-stat-card label="Available" :value="(string) $summary['available']" />
        <x-stat-card label="In Use" :value="(string) $summary['in_use']" />
        <x-stat-card label="Requested" :value="(string) $summary['requested']" />
        <x-stat-card label="Inactive" :value="(string) $summary['inactive']" />
    </div>

    <form method="GET" action="{{ route('reports.assets') }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
        <div class="lg:col-span-2">
            <x-input-label for="search" value="Search" />
            <x-text-input id="search" name="search" class="mt-1 block w-full" :value="$filters['search']" placeholder="Code / nama..." />
        </div>
        <div>
            <x-input-label for="status" value="Status" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                <option value="">Semua</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="source" value="Source" />
            <select id="source" name="source" class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                <option value="">Semua</option>
                @foreach ($sources as $sourceOption)
                    <option value="{{ $sourceOption->value }}" @selected($filters['source'] === $sourceOption->value)>{{ $sourceOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="condition" value="Kondisi" />
            <select id="condition" name="condition" class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                <option value="">Semua</option>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}" @selected($filters['condition'] === $conditionOption->value)>{{ $conditionOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <x-input-label for="category" value="Category" />
                <select id="category" name="category" class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                    <option value="">Semua</option>
                    @foreach ($categories as $categoryOption)
                        <option value="{{ $categoryOption }}" @selected($filters['category'] === $categoryOption)>{{ $categoryOption }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="mt-6 rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm">
        @if ($rows->isEmpty())
            <div class="p-2"><x-empty-state title="Tidak ada asset untuk filter ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Category</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Kondisi</th>
                        <th class="px-4 py-3">Tahun</th>
                        <th class="px-4 py-3">Project Saat Ini</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($rows as $asset)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium">
                                <a href="{{ route('assets.show', $asset) }}" class="text-maroon-950 hover:underline">{{ $asset->code }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $asset->name }}</td>
                            <td class="px-4 py-3">{{ $asset->category }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$asset->source_type" /></td>
                            <td class="px-4 py-3"><x-status-badge :status="$asset->status" /></td>
                            <td class="px-4 py-3"><x-status-badge :status="$asset->condition" /></td>
                            <td class="px-4 py-3">{{ $asset->purchase_year ?: '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($asset->activeAssignment?->project)
                                    {{ $asset->activeAssignment->project->code }}
                                @else
                                    <span class="text-maroon-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
