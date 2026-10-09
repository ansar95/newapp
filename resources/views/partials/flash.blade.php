@if (session('success'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 5000)"
        role="status"
        class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-maroon-300 bg-maroon-50 px-4 py-3 text-sm font-medium text-maroon-950"
    >
        <p>{{ session('success') }}</p>
        <button type="button" class="font-bold text-maroon-800 hover:underline" @click="show = false" aria-label="Dismiss">Tutup</button>
    </div>
@endif

@if (session('error'))
    <div
        x-data="{ show: true }"
        x-show="show"
        role="alert"
        class="mb-4 flex items-start justify-between gap-3 rounded-lg border border-maroon-700 bg-maroon-100 px-4 py-3 text-sm font-medium text-maroon-950"
    >
        <p>{{ session('error') }}</p>
        <button type="button" class="font-bold text-maroon-900 hover:underline" @click="show = false" aria-label="Dismiss">Tutup</button>
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="mb-4 rounded-lg border border-maroon-700 bg-maroon-100 px-4 py-3 text-sm font-medium text-maroon-950">
        <p class="font-bold">Periksa kembali input Anda.</p>
        <ul class="mt-2 list-disc space-y-1 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
