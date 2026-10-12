<x-app-layout>
    <x-slot name="header">Assets</x-slot>

    <x-page-header title="Master Assets" subtitle="Pusat data asset fisik. Project hanya dapat menggunakan asset yang sudah AVAILABLE.">
        <div class="flex flex-wrap items-center gap-2">
            @can('viewAny', \App\Models\Asset::class)
                <a href="{{ route('assets.export') }}"
                   class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
                    Export Excel
                </a>
            @endcan
            @can('create', \App\Models\Asset::class)
                <a href="{{ route('assets.create') }}"
                   class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    + New Asset
                </a>
            @endcan
        </div>
    </x-page-header>

    @can('create', \App\Models\Asset::class)
        <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4 shadow-sm"
             x-data="{ submitting: false }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-maroon-950">Import Asset</h3>
                    <p class="mt-1 text-xs text-maroon-700">
                        Upload file Excel (.xlsx) atau CSV untuk menambah atau memperbarui master asset (termasuk kondisi & tahun pembelian).
                        <a href="{{ route('assets.import.template') }}" class="font-medium text-maroon-900 underline">Download template Excel</a>
                    </p>
                </div>
                <form method="POST" action="{{ route('assets.import') }}" enctype="multipart/form-data"
                      class="flex flex-col gap-2 sm:flex-row sm:items-center"
                      @submit="submitting = true">
                    @csrf
                    <input type="file" name="file" accept=".xlsx,.csv" required
                           class="block w-full text-sm text-maroon-800 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white sm:w-auto">
                    <button type="submit"
                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Importing...' : 'Import Excel/CSV'">
                        Import Excel/CSV
                    </button>
                </form>
            </div>
            <x-input-error :messages="$errors->get('file')" class="mt-2" />
        </div>
    @endcan

    <form method="GET" action="{{ route('assets.index') }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2">
            <x-input-label for="search" value="Search" />
            <x-text-input id="search" type="text" name="search" class="mt-1 block w-full" :value="$search"
                          placeholder="Cari code, nama, atau kategori..." />
        </div>
        <div>
            <x-input-label for="status" value="Status" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($status === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label for="category" value="Category" />
            <select id="category" name="category" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <x-input-label for="source" value="Source" />
                <select id="source" name="source" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                    <option value="">Semua</option>
                    @foreach ($sources as $sourceOption)
                        <option value="{{ $sourceOption->value }}" @selected($source === $sourceOption->value)>{{ $sourceOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="mt-6 rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
                Filter
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        @if ($assets->isEmpty())
            <div class="p-2">
                <x-empty-state
                    title="Belum ada master asset."
                    description="Buat master asset terlebih dahulu sebelum menggunakannya pada project."
                    :action-label="auth()->user()->can('create', \App\Models\Asset::class) ? '+ New Asset' : null"
                    :action-url="auth()->user()->can('create', \App\Models\Asset::class) ? route('assets.create') : null"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Asset Code</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3">Kondisi</th>
                        <th class="px-4 py-3">Tahun</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Project Saat Ini</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($assets as $asset)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium text-maroon-950">{{ $asset->code }}</td>
                            <td class="px-4 py-3">{{ $asset->name }}</td>
                            <td class="px-4 py-3">{{ $asset->category }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$asset->source_type" /></td>
                            <td class="px-4 py-3"><x-status-badge :status="$asset->condition" /></td>
                            <td class="px-4 py-3">{{ $asset->purchase_year ?: '—' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$asset->status" /></td>
                            <td class="px-4 py-3">
                                @if ($asset->activeAssignment?->project)
                                    <a href="{{ route('projects.assets.index', $asset->activeAssignment->project) }}" class="font-medium text-maroon-900 hover:underline">
                                        {{ $asset->activeAssignment->project->code }}
                                    </a>
                                    <p class="text-xs text-maroon-700">Sedang digunakan oleh {{ $asset->activeAssignment->project->code }}</p>
                                @else
                                    <span class="text-maroon-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('assets.show', $asset) }}" class="font-medium text-maroon-900 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600">View</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-maroon-100 px-4 py-3">{{ $assets->links() }}</div>
        @endif
    </div>
</x-app-layout>
