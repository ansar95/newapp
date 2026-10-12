<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Assets</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Assets', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-next-action :action="$context->nextAction" />
        </div>
        <x-workflow-steps :steps="$context->workflowSteps" />
    </div>

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">Project menggunakan asset dari Master Asset.</p>
        <x-helper-text class="mt-1" text="Satu asset fisik hanya boleh digunakan satu project aktif. Asset tidak memengaruhi BOQ, progress, atau payment." />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Sedang Digunakan</p>
            <p class="mt-2 text-xl font-semibold">{{ $activeCount }}</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Sudah Release</p>
            <p class="mt-2 text-xl font-semibold">{{ $releasedCount }}</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total Riwayat</p>
            <p class="mt-2 text-xl font-semibold">{{ $assignments->count() }}</p>
        </div>
    </div>

    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, submitting: false }">
        @can('assign', [\App\Models\ProjectAsset::class, $project])
            <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-maroon-950">Gunakan Asset</h3>
                        <x-helper-text text="Hanya menampilkan asset AVAILABLE yang tidak sedang dipakai project lain." />
                    </div>
                    <button type="button"
                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900"
                            @click="open = !open"
                            x-text="open ? 'Tutup' : '+ Gunakan Asset'">
                        + Gunakan Asset
                    </button>
                </div>

                <form method="POST" action="{{ route('projects.assets.store', $project) }}" class="mt-4 space-y-4"
                      x-show="open" x-cloak
                      @submit="if (!confirm('Gunakan asset ini pada project?')) { $event.preventDefault(); return; } submitting = true">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Asset AVAILABLE *" />
                            <select name="asset_id" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                <option value="">Pilih asset</option>
                                @foreach ($availableAssets as $asset)
                                    <option value="{{ $asset->id }}" @selected((string) old('asset_id') === (string) $asset->id)>
                                        {{ $asset->code }} — {{ $asset->name }} ({{ $asset->source_type->label() }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('asset_id')" class="mt-1" />
                            @if ($availableAssets->isEmpty())
                                <p class="mt-1 text-xs text-maroon-900">Tidak ada asset AVAILABLE. Pastikan master asset sudah AVAILABLE dan tidak sedang IN USE.</p>
                            @endif
                        </div>
                        <div>
                            <x-input-label value="Notes" />
                            <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" placeholder="Catatan penempatan">
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Penanggung Jawab" />
                            <input type="text" name="responsible_name" value="{{ old('responsible_name') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" placeholder="Nama penanggung jawab">
                            <x-input-error :messages="$errors->get('responsible_name')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Jabatan" />
                            <input type="text" name="responsible_position" value="{{ old('responsible_position') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" placeholder="Jabatan penanggung jawab">
                            <x-input-error :messages="$errors->get('responsible_position')" class="mt-1" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                                :disabled="submitting"
                                x-text="submitting ? 'Processing...' : 'Assign Asset'">
                            Assign Asset
                        </button>
                    </div>
                </form>
            </div>
        @endcan

        @if ($assignments->isEmpty())
            <x-empty-state
                title="Belum ada asset yang digunakan pada project ini."
                description="Asset bersifat opsional dan tidak memengaruhi BOQ, payment, atau progress."
            >
                @can('assign', [\App\Models\ProjectAsset::class, $project])
                    <button type="button"
                            class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900"
                            @click="open = true">
                        + Gunakan Asset
                    </button>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-maroon-200 text-sm">
                        <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                        <tr>
                            <th class="px-4 py-3">Asset Code</th>
                            <th class="px-4 py-3">Asset Name</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Penanggung Jawab</th>
                            <th class="px-4 py-3">Assigned Date</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-maroon-100">
                        @foreach ($assignments as $assignment)
                            <tr class="hover:bg-maroon-50">
                                <td class="px-4 py-3 font-medium text-maroon-950">{{ $assignment->asset->code }}</td>
                                <td class="px-4 py-3">{{ $assignment->asset->name }}</td>
                                <td class="px-4 py-3">{{ $assignment->asset->category }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$assignment->asset->source_type" /></td>
                                <td class="px-4 py-3"><x-status-badge :status="$assignment->status" /></td>
                                <td class="min-w-[12rem] px-4 py-3" x-data="{ editing: false }">
                                    <div x-show="!editing">
                                        <div class="font-medium text-maroon-950">{{ $assignment->responsible_name ?? '-' }}</div>
                                        @if ($assignment->responsible_position)
                                            <div class="text-xs text-maroon-700">{{ $assignment->responsible_position }}</div>
                                        @endif
                                        @can('updateResponsible', $assignment)
                                            <button type="button" class="mt-1 text-xs font-medium text-maroon-800 hover:underline" @click="editing = true">Ubah</button>
                                        @endcan
                                    </div>
                                    @can('updateResponsible', $assignment)
                                        <form method="POST" action="{{ route('projects.assets.responsible', [$project, $assignment]) }}" class="space-y-2" x-show="editing" x-cloak>
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="responsible_name" value="{{ $assignment->responsible_name }}" class="w-full rounded-md border-maroon-300 text-sm" placeholder="Nama">
                                            <input type="text" name="responsible_position" value="{{ $assignment->responsible_position }}" class="w-full rounded-md border-maroon-300 text-sm" placeholder="Jabatan">
                                            <div class="flex gap-2">
                                                <button type="submit" class="rounded-md bg-maroon-800 px-3 py-1 text-xs font-medium text-white">Simpan</button>
                                                <button type="button" class="text-xs font-medium text-maroon-800 hover:underline" @click="editing = false">Batal</button>
                                            </div>
                                        </form>
                                    @endcan
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$assignment->assigned_at" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @can('release', $assignment)
                                            @if ($assignment->status === \App\Enums\ProjectAssetStatus::InUse)
                                                <form method="POST" action="{{ route('projects.assets.release', [$project, $assignment]) }}"
                                                      onsubmit="return confirm('Release asset ini agar kembali AVAILABLE?');">
                                                    @csrf
                                                    <button type="submit" class="text-sm font-medium text-maroon-800 hover:underline">Release</button>
                                                </form>
                                            @endif
                                        @endcan
                                        <a href="{{ route('assets.show', $assignment->asset) }}" class="text-sm font-medium text-maroon-900 hover:underline">Master</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
