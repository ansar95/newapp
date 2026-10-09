<x-app-layout>
    <x-slot name="header">{{ $asset->code }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Assets', 'url' => route('assets.index')],
        ['label' => $asset->code, 'url' => null],
    ]" />

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-maroon-950">{{ $asset->name }}</h2>
            <p class="mt-1 text-sm text-maroon-700">{{ $asset->category }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('markAvailable', $asset)
                @if ($asset->status === \App\Enums\AssetStatus::Requested)
                    <form method="POST" action="{{ route('assets.available', $asset) }}"
                          onsubmit="return confirm('Tandai asset sudah tersedia (AVAILABLE)?');">
                        @csrf
                        <button type="submit" class="rounded-md border border-maroon-300 bg-white px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-100">
                            Mark Available
                        </button>
                    </form>
                @endif
            @endcan
            @can('update', $asset)
                <a href="{{ route('assets.edit', $asset) }}"
                   class="rounded-md border border-maroon-300 bg-white px-3 py-1.5 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
                    Edit
                </a>
            @endcan
            @can('delete', $asset)
                <form method="POST" action="{{ route('assets.destroy', $asset) }}"
                      onsubmit="return confirm('Nonaktifkan atau hapus asset ini?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">
                        {{ $asset->isUsedInProjects() ? 'Set Inactive' : 'Delete' }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="mb-6">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-maroon-700">Identitas Asset</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Code</p>
                <p class="mt-2 font-semibold text-maroon-950">{{ $asset->code }}</p>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Brand / Model</p>
                <p class="mt-2 font-semibold text-maroon-950">{{ $asset->brand ?: '—' }} / {{ $asset->model ?: '—' }}</p>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Source</p>
                <div class="mt-2"><x-status-badge :status="$asset->source_type" /></div>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Status</p>
                <div class="mt-2"><x-status-badge :status="$asset->status" /></div>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Kondisi</p>
                <div class="mt-2"><x-status-badge :status="$asset->condition" /></div>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Tahun Pembelian</p>
                <p class="mt-2 font-semibold text-maroon-950">{{ $asset->purchase_year ?: '—' }}</p>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Description</p>
                <p class="mt-2 text-sm text-maroon-800">{{ $asset->description ?: '—' }}</p>
            </div>
        </div>
    </div>

    @if ($asset->source_type === \App\Enums\AssetSourceType::Purchase)
        <div class="mb-6">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-maroon-700">Informasi Permohonan Pembelian</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Nomor Surat</p>
                    <p class="mt-2 font-semibold text-maroon-950">{{ $asset->request_number ?: '—' }}</p>
                </div>
                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Tanggal Surat</p>
                    <p class="mt-2 font-semibold text-maroon-950"><x-date-display :value="$asset->request_date" /></p>
                </div>
                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Status Permohonan</p>
                    <div class="mt-2">
                        @if ($asset->request_status)
                            <x-status-badge :status="$asset->request_status" />
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Pemohon</p>
                    <p class="mt-2 font-semibold text-maroon-950">{{ $asset->requester?->name ?: '—' }}</p>
                </div>
                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">File Surat</p>
                    <p class="mt-2 text-sm">
                        @if ($asset->request_letter_file)
                            <a href="{{ route('assets.request-letter', $asset) }}" class="font-medium text-maroon-900 underline">Download surat</a>
                        @else
                            —
                        @endif
                    </p>
                    @if ($asset->request_description)
                        <p class="mt-2 text-sm text-maroon-700">{{ $asset->request_description }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="mb-6">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-maroon-700">Current Assignment</h3>
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            @if ($currentAssignment)
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-maroon-700">Project</p>
                        <a href="{{ route('projects.assets.index', $currentAssignment->project) }}" class="mt-1 font-semibold text-maroon-950 hover:underline">
                            {{ $currentAssignment->project->code }} — {{ $currentAssignment->project->name }}
                        </a>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-maroon-700">Assigned Date</p>
                        <p class="mt-1 font-semibold text-maroon-950"><x-date-display :value="$currentAssignment->assigned_at" /></p>
                    </div>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-maroon-700">Assigned By</p>
                        <p class="mt-1 font-semibold text-maroon-950">{{ $currentAssignment->assignedBy?->name ?: '—' }}</p>
                    </div>
                </div>
            @else
                <p class="text-sm text-maroon-700">Asset saat ini tidak digunakan oleh project.</p>
            @endif
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="border-b border-maroon-100 px-4 py-3">
            <h3 class="font-semibold text-maroon-950">Riwayat Penggunaan</h3>
            <p class="mt-0.5 text-xs text-maroon-700">Satu asset hanya dapat digunakan satu project aktif pada satu waktu.</p>
        </div>
        @if ($history->isEmpty())
            <div class="p-6 text-sm text-maroon-700">Belum ada riwayat penggunaan.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Assigned</th>
                        <th class="px-4 py-3">Released</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($history as $usage)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3">
                                @if ($usage->project->trashed())
                                    <span class="font-medium text-maroon-900">{{ $usage->project->code }} — {{ $usage->project->name }}</span>
                                    <span class="ml-1 text-xs text-maroon-700">(project dihapus)</span>
                                @else
                                    <a href="{{ route('projects.assets.index', $usage->project) }}" class="font-medium text-maroon-900 hover:underline">
                                        {{ $usage->project->code }} — {{ $usage->project->name }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$usage->assigned_at" /></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($usage->released_at)
                                    <x-date-display :value="$usage->released_at" />
                                @else
                                    sekarang
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-status-badge :status="$usage->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
