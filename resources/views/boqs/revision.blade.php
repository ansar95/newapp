<x-app-layout>
    <x-slot name="header">BOQ Revision</x-slot>
    <x-slot name="subheader">{{ $boq->code }}</x-slot>

    <div class="mb-4">
        <a href="{{ route('boqs.show', $boq) }}" class="text-sm text-maroon-700 hover:underline">&larr; Back to BOQ</a>
    </div>

    @if ($revision)
        <div class="mb-6 rounded-xl border border-maroon-300 bg-maroon-100 p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-900">Revision Required</p>
            <p class="mt-1 text-lg font-semibold text-maroon-950">{{ $revision->revision_code }}</p>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-maroon-700">Status</dt><dd class="mt-1"><x-status-badge :status="$revision->status" /></dd></div>
                <div><dt class="text-maroon-700">Date</dt><dd class="mt-1 font-medium"><x-date-display :value="$revision->created_at" /></dd></div>
                <div class="sm:col-span-2"><dt class="text-maroon-700">Reason</dt><dd class="mt-1 font-medium">{{ $revision->reason }}</dd></div>
                <div class="sm:col-span-2">
                    <dt class="text-maroon-700">Items to Revise</dt>
                    <dd class="mt-1">
                        <ul class="list-disc space-y-1 pl-5 font-medium text-maroon-900">
                            @foreach ($revision->revisionItems as $revisionItem)
                                <li>{{ $revisionItem->item?->name ?? 'Item' }} <span class="font-normal text-maroon-700">({{ $revisionItem->item?->cogs?->name }})</span></li>
                            @endforeach
                        </ul>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            @if ($boq->status === \App\Enums\BoqStatus::RevisionRequired)
                @can('prepare', [\App\Models\BoqRevision::class, $boq])
                    <form method="POST" action="{{ route('boqs.revision.prepare', $boq) }}" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <button class="rounded-md bg-maroon-700 px-4 py-2 text-sm text-white disabled:opacity-60" :disabled="submitting"
                                x-text="submitting ? 'Processing...' : 'Open Revision'">Open Revision</button>
                    </form>
                @endcan
            @endif
            @if ($boq->status === \App\Enums\BoqStatus::Draft)
                @can('submit', [\App\Models\BoqRevision::class, $boq])
                    <div class="w-full rounded-xl border border-maroon-300 bg-maroon-100 p-4 sm:w-auto">
                        <p class="mb-2 text-sm text-maroon-900">Item revisi telah diperbarui. Kirim ulang untuk approval.</p>
                        <form method="POST" action="{{ route('boqs.revision.submit', $boq) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <button class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-60" :disabled="submitting"
                                    x-text="submitting ? 'Processing...' : 'Submit BOQ Again'">Submit BOQ Again</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>

        <div class="space-y-4">
            @forelse ($revision->revisionItems as $revisionItem)
                @php $item = $revisionItem->item; @endphp
                <div class="rounded-xl border border-maroon-300 bg-white p-5 shadow-sm">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-maroon-950">{{ $item?->name }}</h3>
                        <x-status-badge status="REVISION_REQUIRED" />
                    </div>
                    <p class="text-sm text-maroon-700">COGS: {{ $item?->cogs?->name }} · {{ $item?->code }}</p>
                    @if ($item?->notes)
                        <p class="text-sm text-maroon-700">Keterangan: {{ $item->notes }}</p>
                    @endif
                    <p class="mt-2 text-sm"><span class="font-medium">Reason:</span> {{ $revisionItem->reason }}</p>

                    @if ($boq->status === \App\Enums\BoqStatus::Draft && $item)
                        @can('update', [\App\Models\BoqRevision::class, $boq])
                            <form method="POST" action="{{ route('boqs.revision.items.update', $item) }}" class="mt-4 grid gap-3 sm:grid-cols-5"
                                  x-data="{ submitting: false }" @submit="submitting = true">
                                @csrf @method('PATCH')
                                <div>
                                    <x-input-label value="Qty *" />
                                    <input type="number" step="0.01" min="0" name="quantity" value="{{ old('quantity', $item->quantity) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                </div>
                                <div>
                                    <x-input-label value="Unit *" />
                                    <input type="text" name="unit" value="{{ old('unit', $item->unit) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                </div>
                                <div>
                                    <x-input-label value="Harga Material *" />
                                    <input type="number" step="0.01" min="0" name="material_unit_price" value="{{ old('material_unit_price', $item->material_unit_price) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                </div>
                                <div>
                                    <x-input-label value="Harga Jasa *" />
                                    <input type="number" step="0.01" min="0" name="service_unit_price" value="{{ old('service_unit_price', $item->service_unit_price) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                </div>
                                <div class="sm:col-span-4">
                                    <x-input-label value="Keterangan" />
                                    <input type="text" name="notes" maxlength="255" value="{{ $item->notes }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                                </div>
                                <div class="flex items-end">
                                    <button class="w-full rounded-md bg-maroon-800 px-3 py-2 text-sm text-white disabled:opacity-60" :disabled="submitting"
                                            x-text="submitting ? 'Processing...' : 'Update Item'">Update Item</button>
                                </div>
                            </form>
                            <p class="mt-2 text-xs text-maroon-700">
                                Current total: <x-money :amount="$item->total_price" />
                                · Material <x-money :amount="$item->materialTotal()" /> · Jasa <x-money :amount="$item->serviceTotal()" />
                            </p>
                        @endcan
                    @endif
                </div>
            @empty
                <p class="text-sm text-maroon-700">Tidak ada item revisi.</p>
            @endforelse
        </div>
    @else
        <x-empty-state title="Belum ada revision history." description="Revision history akan muncul setelah reviewer meminta revisi." />
    @endif
</x-app-layout>
