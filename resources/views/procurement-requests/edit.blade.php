<x-app-layout>
    <x-slot name="header">{{ $procurementRequest->request_number }} — Edit</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Pengadaan', 'url' => route('projects.procurement.index', $project)],
        ['label' => $procurementRequest->request_number, 'url' => route('procurement-requests.show', $procurementRequest)],
        ['label' => 'Edit', 'url' => null],
    ]" />

    <div class="mx-auto max-w-5xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Edit Permintaan {{ $procurementRequest->request_number }}</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Hanya permintaan berstatus Draft yang dapat diubah. Daftar item akan diganti sesuai isian di bawah.</p>

        <form method="POST" action="{{ route('procurement-requests.update', $procurementRequest) }}"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')
            @include('procurement-requests._form', ['type' => $procurementRequest->type])
            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('procurement-requests.show', $procurementRequest) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Simpan Perubahan'">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
