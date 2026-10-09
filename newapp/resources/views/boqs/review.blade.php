<x-app-layout>
    <x-slot name="header">Review BOQ</x-slot>
    <x-slot name="subheader">{{ $boq->code }} · Revision {{ str_pad((string) $boq->revision_number, 2, '0', STR_PAD_LEFT) }}</x-slot>

    <div class="mb-4">
        <a href="{{ route('boqs.show', $boq) }}" class="text-sm text-maroon-700 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600">&larr; Back</a>
    </div>

    <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm" x-data="{ showRevision: false, confirming: false, submittingRevision: false }">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-maroon-100 pb-4">
            <div>
                <h2 class="text-xl font-semibold text-maroon-950">{{ $boq->code }}</h2>
                <p class="mt-1 text-sm text-maroon-700">{{ $project->code }} — {{ $project->name }}</p>
                <p class="mt-1 text-sm text-maroon-700">Revision {{ str_pad((string) $boq->revision_number, 2, '0', STR_PAD_LEFT) }}</p>
            </div>
            <div class="text-right">
                <x-status-badge :status="$boq->status" />
                <p class="mt-2 text-lg font-semibold text-maroon-950"><x-money :amount="$boq->totalAmount()" /></p>
            </div>
        </div>

        <div class="mt-6 space-y-4">
            @foreach ($boq->cogs as $cogs)
                <div class="rounded-lg border border-maroon-100 p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold uppercase tracking-wide text-maroon-950">{{ $cogs->name }}</h3>
                        <p class="text-sm font-medium">COGS TOTAL: <x-money :amount="$cogs->totalAmount()" /></p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-xs uppercase text-maroon-700">
                            <tr>
                                <th class="py-1 pr-3">Item</th>
                                <th class="py-1 pr-3">Qty</th>
                                <th class="py-1 pr-3">Unit</th>
                                <th class="py-1 pr-3">Unit Price</th>
                                <th class="py-1">Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($cogs->items as $item)
                                <tr class="border-t border-maroon-50">
                                    <td class="py-2 pr-3">{{ $item->name }} <span class="text-xs text-maroon-400">({{ $item->code }})</span></td>
                                    <td class="py-2 pr-3">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td class="py-2 pr-3">{{ $item->unit }}</td>
                                    <td class="py-2 pr-3"><x-money :amount="$item->unit_price" /></td>
                                    <td class="py-2 font-medium"><x-money :amount="$item->total_price" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 rounded-lg border border-maroon-900 bg-maroon-800 px-4 py-3 text-white">
            <div class="flex items-center justify-between">
                <span class="text-sm uppercase tracking-wide text-maroon-200">TOTAL BOQ</span>
                <span class="text-lg font-semibold"><x-money :amount="$boq->totalAmount()" /></span>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            @can('approve', $boq)
                <div class="rounded-lg border border-maroon-300 bg-maroon-100 p-4">
                    <form method="POST" action="{{ route('boqs.confirm', $boq) }}" @submit="confirming = true">
                        @csrf
                        <input type="hidden" name="review_notes" value="Confirmed">
                        <button type="submit"
                                class="w-full rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900 disabled:opacity-60"
                                :disabled="confirming"
                                x-text="confirming ? 'Processing...' : 'CONFIRM BOQ'">
                            CONFIRM BOQ
                        </button>
                    </form>
                    <p class="mt-2 text-xs leading-relaxed text-maroon-900">
                        BOQ disetujui dan dapat dilanjutkan ke payment planning.
                    </p>
                </div>
            @endcan
            @can('requestRevision', $boq)
                <div class="rounded-lg border border-maroon-300 bg-maroon-100 p-4">
                    <button type="button" @click="showRevision = true"
                            class="w-full rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-300">
                        REQUEST REVISION
                    </button>
                    <p class="mt-2 text-xs leading-relaxed text-maroon-900">
                        Kembalikan BOQ untuk diperbaiki. Pilih item yang perlu direvisi.
                    </p>
                </div>
            @endcan
        </div>

        <div x-show="showRevision" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-maroon-800/50 p-4" role="dialog" aria-modal="true" aria-labelledby="revision-title">
            <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @keydown.escape.window="showRevision = false">
                <h3 id="revision-title" class="text-lg font-semibold text-maroon-950">Request Revision</h3>
                <p class="mt-1 text-sm text-maroon-700">Pilih item pekerjaan yang perlu diperbaiki.</p>
                <form method="POST" action="{{ route('boqs.request-revision', $boq) }}" class="mt-4 space-y-4" @submit="submittingRevision = true">
                    @csrf
                    <div>
                        <x-input-label for="reason" value="Revision Reason *" />
                        <textarea id="reason" name="reason" rows="3" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required
                                  placeholder="Contoh: Harga perlu disesuaikan.">{{ old('reason') }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>
                    <div>
                        <p class="mb-2 text-sm font-medium text-maroon-800">Items to Revise *</p>
                        <div class="max-h-64 space-y-2 overflow-y-auto rounded-md border border-maroon-200 p-3">
                            @foreach ($boq->cogs as $cogs)
                                <p class="pt-2 text-xs font-semibold uppercase text-maroon-700 first:pt-0">{{ $cogs->name }}</p>
                                @foreach ($cogs->items as $item)
                                    <label class="flex items-start gap-2 text-sm">
                                        <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="mt-1 rounded border-maroon-300 text-maroon-950 focus:ring-maroon-600"
                                               @checked(collect(old('item_ids', []))->contains($item->id))>
                                        <span>{{ $item->name }}</span>
                                    </label>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showRevision = false" class="rounded-md border border-maroon-300 px-4 py-2 text-sm">Cancel</button>
                        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                                :disabled="submittingRevision"
                                x-text="submittingRevision ? 'Processing...' : 'Request Revision'">
                            Request Revision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
