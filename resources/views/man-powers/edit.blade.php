<x-app-layout>
    <x-slot name="header">Man Power — {{ $manPower->code }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Man Power', 'url' => route('man-powers.index')],
        ['label' => $manPower->code, 'url' => null],
    ]" />

    <x-page-header :title="'Edit '.$manPower->code" subtitle="Perubahan upah harian di master tidak mengubah upah yang sudah tercatat di project." />

    <form method="POST" action="{{ route('man-powers.update', $manPower) }}"
          class="mb-6 space-y-4 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm"
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label value="Nama *" />
                <input type="text" name="name" value="{{ old('name', $manPower->name) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Posisi *" />
                <input type="text" name="position" value="{{ old('position', $manPower->position) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                <x-input-error :messages="$errors->get('position')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Upah Harian (Rp) *" />
                <input type="number" name="daily_wage" value="{{ old('daily_wage', $manPower->daily_wage) }}" min="0" step="0.01" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                <x-input-error :messages="$errors->get('daily_wage')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Telepon" />
                <input type="text" name="phone" value="{{ old('phone', $manPower->phone) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>
            <div>
                <x-input-label value="Status *" />
                <select name="is_active" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                    <option value="1" @selected((string) old('is_active', (int) $manPower->is_active) === '1')>Aktif</option>
                    <option value="0" @selected((string) old('is_active', (int) $manPower->is_active) === '0')>Nonaktif</option>
                </select>
                <x-helper-text class="mt-1" text="Man power nonaktif tidak dapat ditambahkan ke project baru." />
            </div>
            <div>
                <x-input-label value="Catatan" />
                <input type="text" name="notes" value="{{ old('notes', $manPower->notes) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <a href="{{ route('man-powers.index') }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">Batal</a>
            <button type="submit"
                    class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                    :disabled="submitting"
                    x-text="submitting ? 'Processing...' : 'Simpan'">
                Simpan
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <h3 class="border-b border-maroon-100 px-4 py-3 font-semibold text-maroon-950">Riwayat Project</h3>
        @if ($manPower->projectManPowers->isEmpty())
            <div class="p-6 text-sm text-maroon-700">Belum pernah ditambahkan ke project.</div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Posisi</th>
                        <th class="px-4 py-3 text-right">Hari Kerja</th>
                        <th class="px-4 py-3 text-right">Upah Harian</th>
                        <th class="px-4 py-3 text-right">Total Gaji</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($manPower->projectManPowers as $member)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($member->project->trashed())
                                    {{ $member->project->code }} <span class="text-xs text-maroon-700">(project dihapus)</span>
                                @else
                                    <a href="{{ route('projects.man-power.index', $member->project) }}" class="font-medium text-maroon-900 hover:underline">{{ $member->project->code }} — {{ $member->project->name }}</a>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $member->position }}</td>
                            <td class="px-4 py-3 text-right">{{ $member->work_days }}</td>
                            <td class="px-4 py-3 text-right"><x-money :amount="$member->daily_wage" /></td>
                            <td class="px-4 py-3 text-right"><x-money :amount="$member->totalSalary()" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
