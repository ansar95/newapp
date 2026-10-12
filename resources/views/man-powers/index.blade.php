<x-app-layout>
    <x-slot name="header">Man Power</x-slot>

    <x-page-header title="Master Man Power" subtitle="Data tenaga kerja beserta posisi dan upah harian. Upah dapat disesuaikan saat ditambahkan ke project.">
        <a href="{{ route('man-powers.export') }}"
           class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
            Export Excel
        </a>
    </x-page-header>

    @can('create', \App\Models\ManPower::class)
        <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4 shadow-sm" x-data="{ submitting: false }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-maroon-950">Import Man Power</h3>
                    <p class="mt-1 text-xs text-maroon-700">
                        Upload file Excel (.xlsx) atau CSV untuk menambah atau memperbarui master man power. Kode kosong = dibuat baru dengan kode otomatis; kode yang sudah ada = datanya diperbarui.
                        <a href="{{ route('man-powers.import.template') }}" class="font-medium text-maroon-900 underline">Download template Excel</a>
                    </p>
                </div>
                <form method="POST" action="{{ route('man-powers.import') }}" enctype="multipart/form-data"
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

        <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm"
             x-data="{ open: {{ $errors->hasAny(['name', 'position', 'daily_wage', 'phone', 'notes']) ? 'true' : 'false' }}, submitting: false }">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-maroon-950">Tambah Man Power</h3>
                    <x-helper-text text="Kode dibuat otomatis (MP-001, MP-002, …)." />
                </div>
                <button type="button"
                        class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900"
                        @click="open = !open"
                        x-text="open ? 'Tutup' : '+ Tambah Man Power'">
                    + Tambah Man Power
                </button>
            </div>

            <form method="POST" action="{{ route('man-powers.store') }}" class="mt-4 space-y-4"
                  x-show="open" x-cloak @submit="submitting = true">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <x-input-label value="Nama *" />
                        <input type="text" name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Posisi *" />
                        <input type="text" name="position" value="{{ old('position') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" placeholder="Tukang, Helper, Mandor…" required>
                        <x-input-error :messages="$errors->get('position')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Upah Harian (Rp) *" />
                        <input type="number" name="daily_wage" value="{{ old('daily_wage') }}" min="0" step="0.01" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        <x-input-error :messages="$errors->get('daily_wage')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Telepon" />
                        <input type="text" name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <x-input-label value="Catatan" />
                        <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Processing...' : 'Simpan'">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    @endcan

    <form method="GET" action="{{ route('man-powers.index') }}" class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
        <div class="lg:col-span-2">
            <x-input-label for="search" value="Search" />
            <x-text-input id="search" type="text" name="search" class="mt-1 block w-full" :value="$search"
                          placeholder="Cari kode, nama, atau posisi..." />
        </div>
        <div>
            <x-input-label for="status" value="Status" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua</option>
                <option value="active" @selected($status === 'active')>Aktif</option>
                <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
            </select>
        </div>
        <div>
            <button type="submit" class="rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">Filter</button>
        </div>
    </form>

    @if ($manPowers->isEmpty())
        <x-empty-state title="Belum ada data man power." description="Tambahkan man power agar dapat digunakan pada project." />
    @else
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Posisi</th>
                        <th class="px-4 py-3 text-right">Upah Harian</th>
                        <th class="px-4 py-3">Telepon</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Project</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($manPowers as $manPower)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium text-maroon-950">{{ $manPower->code }}</td>
                            <td class="px-4 py-3">{{ $manPower->name }}</td>
                            <td class="px-4 py-3">{{ $manPower->position }}</td>
                            <td class="px-4 py-3 text-right"><x-money :amount="$manPower->daily_wage" /></td>
                            <td class="px-4 py-3">{{ $manPower->phone ?? '-' }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$manPower->is_active ? 'ACTIVE' : 'INACTIVE'" /></td>
                            <td class="px-4 py-3 text-right">{{ $manPower->project_man_powers_count }}</td>
                            <td class="px-4 py-3">
                                @can('update', $manPower)
                                    <a href="{{ route('man-powers.edit', $manPower) }}" class="text-sm font-medium text-maroon-900 hover:underline">Edit</a>
                                @else
                                    -
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $manPowers->links() }}</div>
    @endif
</x-app-layout>
