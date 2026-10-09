<x-app-layout>
    <x-slot name="header">{{ $project->code }} — New BMHP Purchase</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'BMHP', 'url' => route('projects.bmhp.index', $project)],
        ['label' => 'New Purchase', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Header Pembelian BMHP</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Setelah disimpan, tambahkan item BMHP dan alokasikan ke Item BOQ pada halaman detail pembelian.</p>

        <form method="POST" action="{{ route('projects.bmhp.store', $project) }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @include('bmhp-purchases._header-form', ['purchase' => null])
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('projects.bmhp.index', $project) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Save & Lanjut ke Item'">
                    Save &amp; Lanjut ke Item
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
