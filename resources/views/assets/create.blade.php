<x-app-layout>
    <x-slot name="header">New Asset</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Assets', 'url' => route('assets.index')],
        ['label' => 'New Asset', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data" class="space-y-4"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @include('assets._form', ['asset' => null])
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('assets.index') }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Save Asset'">
                    Save Asset
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
