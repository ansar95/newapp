<x-app-layout>
    <x-slot name="header">Edit Master BMHP</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Master BMHP', 'url' => route('master-bmhps.index')],
        ['label' => $masterBmhp->code, 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('master-bmhps.update', $masterBmhp) }}" class="space-y-4"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')
            @include('master-bmhps._form')
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('master-bmhps.index') }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Update BMHP'">
                    Update BMHP
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
