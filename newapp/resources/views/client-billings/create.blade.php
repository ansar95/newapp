<x-app-layout>
    <x-slot name="header">{{ $project->code }} — New Billing</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Penagihan', 'url' => route('projects.billing.index', $project)],
        ['label' => 'New Billing', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Penagihan Client Baru</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Penagihan dibuat sebagai DRAFT, lalu di-submit dan ditandai SENT saat dikirim ke client.</p>

        <form method="POST" action="{{ route('client-billings.store', $project) }}" enctype="multipart/form-data"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @include('client-billings._form')
            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('projects.billing.index', $project) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Simpan Draft'">
                    Simpan Draft
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
