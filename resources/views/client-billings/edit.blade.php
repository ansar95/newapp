<x-app-layout>
    <x-slot name="header">{{ $billing->billing_number }} — Edit</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Penagihan', 'url' => route('projects.billing.index', $project)],
        ['label' => $billing->billing_number, 'url' => route('client-billings.show', $billing)],
        ['label' => 'Edit', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Edit Penagihan {{ $billing->billing_number }}</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Hanya penagihan berstatus DRAFT yang dapat diubah.</p>

        <form method="POST" action="{{ route('client-billings.update', $billing) }}" enctype="multipart/form-data"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')
            @include('client-billings._form')
            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('client-billings.show', $billing) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Simpan Perubahan'">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
