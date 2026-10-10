<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Permintaan {{ $type->label() }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Pengadaan', 'url' => route('projects.procurement.index', $project)],
        ['label' => 'Permintaan '.$type->label(), 'url' => null],
    ]" />

    <div class="mx-auto max-w-5xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Permintaan {{ $type->label() }} Baru</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Permintaan disimpan sebagai Draft, lalu diajukan untuk disetujui Approver dan dibayar oleh Finance.</p>

        <form method="POST" action="{{ route('projects.procurement.store', $project) }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <input type="hidden" name="type" value="{{ $type->value }}">
            @include('procurement-requests._form', ['procurementRequest' => null])
            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('projects.procurement.index', $project) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Simpan Draft'">
                    Simpan Draft
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
