<x-app-layout>
    <x-slot name="header">{{ $purchase->purchase_number }} — Edit Header</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'BMHP', 'url' => route('projects.bmhp.index', $project)],
        ['label' => $purchase->purchase_number, 'url' => route('bmhp-purchases.show', $purchase)],
        ['label' => 'Edit', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('bmhp-purchases.update', $purchase) }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')
            @include('bmhp-purchases._header-form', ['purchase' => $purchase])
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('bmhp-purchases.show', $purchase) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Update Header'">
                    Update Header
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
