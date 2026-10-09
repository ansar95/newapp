<x-app-layout>
    <x-slot name="header">Edit {{ $asset->code }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Assets', 'url' => route('assets.index')],
        ['label' => $asset->code, 'url' => route('assets.show', $asset)],
        ['label' => 'Edit', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('assets.update', $asset) }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')
            @include('assets._form', ['asset' => $asset])
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('assets.show', $asset) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Update Asset'">
                    Update Asset
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
