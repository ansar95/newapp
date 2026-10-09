<x-app-layout>
    <x-slot name="header">Master BMHP</x-slot>

    <x-page-header title="Master BMHP" subtitle="Daftar Bahan Medis Habis Pakai / material consumable yang dapat dipilih saat mencatat pembelian BMHP di project.">
        @can('import', \App\Models\MasterBmhp::class)
            <a href="{{ route('master-bmhps.import') }}"
               class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                Import CSV
            </a>
        @endcan
        @can('create', \App\Models\MasterBmhp::class)
            <a href="{{ route('master-bmhps.create') }}"
               class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                + New BMHP
            </a>
        @endcan
    </x-page-header>

    <form method="GET" action="{{ route('master-bmhps.index') }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        <div class="lg:col-span-2">
            <x-input-label for="search" value="Search" />
            <x-text-input id="search" name="search" class="mt-1 block w-full" :value="$search" placeholder="Cari code atau nama..." />
        </div>
        <div>
            <x-input-label for="category" value="Kategori" />
            <select id="category" name="category" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption }}" @selected($category === $categoryOption)>{{ $categoryOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                    <option value="">Semua</option>
                    @foreach ($statuses as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected($status === $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="mt-6 rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Filter</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        @if ($masterBmhps->isEmpty())
            <div class="p-2">
                <x-empty-state
                    title="Belum ada Master BMHP."
                    description="Buat Master BMHP agar barang tidak perlu diketik berulang kali saat mencatat pembelian."
                    :action-label="auth()->user()->can('create', \App\Models\MasterBmhp::class) ? '+ New BMHP' : null"
                    :action-url="auth()->user()->can('create', \App\Models\MasterBmhp::class) ? route('master-bmhps.create') : null"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Satuan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Dipakai</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($masterBmhps as $masterBmhp)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium text-maroon-950">{{ $masterBmhp->code }}</td>
                            <td class="px-4 py-3">
                                {{ $masterBmhp->name }}
                                @if ($masterBmhp->description)
                                    <span class="mt-0.5 block text-xs text-maroon-700">{{ \Illuminate\Support\Str::limit($masterBmhp->description, 80) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $masterBmhp->category }}</td>
                            <td class="px-4 py-3">{{ $masterBmhp->unit }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$masterBmhp->status" /></td>
                            <td class="px-4 py-3">{{ $masterBmhp->purchase_details_count }}x</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    @can('update', $masterBmhp)
                                        <a href="{{ route('master-bmhps.edit', $masterBmhp) }}" class="font-medium text-maroon-900 hover:underline">Edit</a>
                                    @endcan
                                    @can('delete', $masterBmhp)
                                        <form method="POST" action="{{ route('master-bmhps.destroy', $masterBmhp) }}"
                                              onsubmit="return confirm('{{ $masterBmhp->purchase_details_count > 0 ? 'BMHP sudah dipakai, status akan diubah menjadi INACTIVE. Lanjutkan?' : 'Hapus Master BMHP ini?' }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-maroon-700 hover:underline">
                                                {{ $masterBmhp->purchase_details_count > 0 ? 'Nonaktifkan' : 'Hapus' }}
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-maroon-100 px-4 py-3">{{ $masterBmhps->links() }}</div>
        @endif
    </div>
</x-app-layout>
