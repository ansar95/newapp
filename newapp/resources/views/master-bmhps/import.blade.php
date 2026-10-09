<x-app-layout>
    <x-slot name="header">Import Master BMHP</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Master BMHP', 'url' => route('master-bmhps.index')],
        ['label' => 'Import CSV', 'url' => null],
    ]" />

    <x-page-header title="Import Master BMHP" subtitle="Tambah atau perbarui banyak Master BMHP sekaligus dari file CSV. Data baru disimpan setelah Anda mengonfirmasi preview." />

    @if ($preview === null)
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-maroon-200 bg-white p-6 shadow-sm lg:col-span-2">
                <h3 class="text-base font-semibold text-maroon-950">Upload File CSV</h3>
                <form method="POST" action="{{ route('master-bmhps.import.preview') }}" enctype="multipart/form-data" class="mt-4"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <x-input-label for="file" value="File CSV (maks 2 MB, {{ \App\Actions\Bmhp\ParseMasterBmhpImport::MAX_ROWS }} baris) *" />
                    <input id="file" type="file" name="file" accept=".csv,text/csv" required
                           class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    <div class="mt-4 flex flex-wrap justify-end gap-2">
                        <a href="{{ route('master-bmhps.index') }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Kembali</a>
                        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                                :disabled="submitting" x-text="submitting ? 'Membaca file...' : 'Preview Import'">Preview Import</button>
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
                <h3 class="text-base font-semibold text-maroon-950">Format File</h3>
                <a href="{{ route('master-bmhps.import.template') }}"
                   class="mt-3 inline-flex rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Download Template</a>
                <ul class="mt-4 list-disc space-y-1.5 pl-5 text-sm text-maroon-800">
                    <li>Kolom: <span class="font-medium">kode, nama*, kategori*, satuan*, deskripsi, status</span>.</li>
                    <li><span class="font-medium">kode</span> kosong = BMHP baru dengan kode otomatis. Kode yang sudah ada = data tersebut diperbarui.</li>
                    <li><span class="font-medium">kategori</span>: {{ implode(', ', $categories) }}.</li>
                    <li><span class="font-medium">status</span>: ACTIVE atau INACTIVE. Kosong = ACTIVE untuk data baru, tidak berubah untuk data lama.</li>
                    <li>Dari Excel: <span class="font-medium">File → Save As → CSV</span>. Pemisah koma maupun titik koma didukung.</li>
                </ul>
            </div>
        </div>
    @else
        <div class="mb-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Baru" :value="$counts['create']" hint="Akan dibuat" />
            <x-stat-card label="Update" :value="$counts['update']" hint="Data lama diperbarui" />
            <x-stat-card label="Tidak Berubah" :value="$counts['unchanged']" hint="Dilewati" />
            <x-stat-card label="Error" :value="$counts['error']" hint="Dilewati, perbaiki di file" />
        </div>

        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-maroon-100 px-4 py-3">
                <div>
                    <h3 class="text-base font-semibold text-maroon-950">Preview: {{ $preview['filename'] }}</h3>
                    <p class="text-xs text-maroon-700">
                        Belum ada data yang disimpan.
                        @if ($counts['error'] > 0) Baris error tidak ikut diimport. @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('master-bmhps.import.cancel') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-maroon-300 px-4 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Batal / Upload Ulang</button>
                    </form>
                    @if ($counts['create'] + $counts['update'] > 0)
                        <form method="POST" action="{{ route('master-bmhps.import.confirm') }}"
                              onsubmit="return confirm('Import {{ $counts['create'] }} BMHP baru dan perbarui {{ $counts['update'] }} BMHP?');">
                            @csrf
                            <input type="hidden" name="token" value="{{ $preview['token'] }}">
                            <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">
                                Konfirmasi Import ({{ $counts['create'] + $counts['update'] }})
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Baris</th>
                        <th class="px-4 py-3">Aksi</th>
                        <th class="px-4 py-3">Kode</th>
                        <th class="min-w-48 px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Satuan</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="min-w-64 px-4 py-3">Keterangan</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($rows as $row)
                        <tr @class(['bg-maroon-50' => $row['action'] === 'error', 'text-maroon-400' => $row['action'] === 'unchanged'])>
                            <td class="px-4 py-3 tabular-nums text-maroon-700">{{ $row['line'] }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-bold ring-1 ring-inset',
                                    'bg-maroon-800 text-white ring-maroon-900' => $row['action'] === 'create',
                                    'bg-maroon-100 text-maroon-900 ring-maroon-300' => $row['action'] === 'update',
                                    'bg-white text-maroon-700 ring-maroon-200' => $row['action'] === 'unchanged',
                                    'bg-white text-maroon-900 ring-maroon-500' => $row['action'] === 'error',
                                ])>
                                    {{ ['create' => 'BARU', 'update' => 'UPDATE', 'unchanged' => 'TIDAK BERUBAH', 'error' => 'ERROR'][$row['action']] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-medium">{{ $row['code'] ?? 'Otomatis' }}</td>
                            <td class="px-4 py-3">
                                {{ $row['name'] !== '' ? $row['name'] : '-' }}
                                @if ($row['action'] === 'update' && $row['existing_name'] !== $row['name'])
                                    <span class="block text-xs text-maroon-700">Sebelumnya: {{ $row['existing_name'] }}</span>
                                @endif
                                @if ($row['description'])
                                    <span class="block text-xs text-maroon-700">{{ \Illuminate\Support\Str::limit($row['description'], 80) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $row['category'] !== '' ? $row['category'] : '-' }}</td>
                            <td class="px-4 py-3">{{ $row['unit'] !== '' ? $row['unit'] : '-' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $row['status'] ?? ($row['action'] === 'create' ? 'ACTIVE' : '-') }}</td>
                            <td class="px-4 py-3 text-xs">
                                @foreach ($row['errors'] as $message)
                                    <p class="font-semibold text-maroon-900">{{ $message }}</p>
                                @endforeach
                                @foreach ($row['warnings'] as $message)
                                    <p class="text-maroon-700">Perhatian: {{ $message }}</p>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
