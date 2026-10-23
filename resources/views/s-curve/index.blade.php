<x-app-layout>
    <x-slot name="header">Kurva S</x-slot>

    <x-page-header
        title="Kurva S"
        subtitle="Perbandingan rencana awal, rebaseline, dan progress fisik satu proyek."
    />

    <form method="GET" action="{{ route('s-curve.index') }}" class="mb-6 rounded-xl border border-maroon-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
                <label for="kurva-project" class="text-sm font-medium text-maroon-800">Proyek</label>
                <select id="kurva-project" name="project" class="mt-1 block w-full rounded-md border-maroon-300 text-sm text-maroon-950 shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                    <option value="">Pilih proyek</option>
                    @foreach ($projects as $projectOption)
                        <option value="{{ $projectOption->id }}" @selected($selectedProject?->id === $projectOption->id)>
                            {{ $projectOption->code }} — {{ $projectOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Tampilkan</button>
        </div>
    </form>

    @if ($panel)
        <div class="rounded-xl border border-maroon-200 bg-maroon-50/40 p-4 sm:p-6">
            @include('s-curve._panel', $panel)
        </div>
    @else
        <x-empty-state
            title="Pilih proyek"
            description="Kurva S ditampilkan untuk satu proyek. Setiap proyek punya bobot dan jadwal sendiri."
        />
    @endif
</x-app-layout>
