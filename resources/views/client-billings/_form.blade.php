@php
    /** @var \App\Models\ClientBilling|null $billing */
    $initialTermId = old('project_term_id', $selectedTermId ?? (count($termOptions) === 1 ? $termOptions[0]['id'] : null));
@endphp

<div class="space-y-4"
     x-data="{
         terms: @js(collect($termOptions)->keyBy('id')),
         termId: @js($initialTermId !== null ? (string) $initialTermId : ''),
         amount: @js((string) old('billing_amount', $billing ? (float) $billing->billing_amount : '')),
         get term() { return this.terms[this.termId] ?? null; },
         get exceeds() { return this.term !== null && Math.round((parseFloat(this.amount) || 0) * 100) > Math.round(this.term.remaining * 100); },
         rupiah(value) { return 'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
     }">
    <div>
        <x-input-label for="project_term_id" value="Termin *" />
        @if ($billing)
            <p class="mt-1 rounded-md bg-maroon-50 px-3 py-2 text-sm font-medium text-maroon-950">{{ $billing->term?->displayName() }}</p>
        @else
            <select id="project_term_id" name="project_term_id" x-model="termId" required
                    class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Pilih termin READY</option>
                @foreach ($termOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            @if (count($termOptions) === 0)
                <p class="mt-1 text-xs text-maroon-900">Belum ada termin READY. Tandai termin sebagai READY di tab Penagihan.</p>
            @endif
        @endif
        <x-input-error :messages="$errors->get('project_term_id')" class="mt-1" />
    </div>

    <dl class="grid gap-3 rounded-lg border border-maroon-200 bg-maroon-50 p-4 text-sm sm:grid-cols-3" x-show="term" x-cloak>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Nilai Termin</dt>
            <dd class="mt-1 font-semibold text-maroon-950" x-text="term ? rupiah(term.amount) : '-'"></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Sudah Ditagih / Diproses</dt>
            <dd class="mt-1 font-semibold text-maroon-950" x-text="term ? rupiah(term.reserved) : '-'"></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Sisa Termin</dt>
            <dd class="mt-1 font-semibold text-maroon-950" x-text="term ? rupiah(term.remaining) : '-'"></dd>
        </div>
    </dl>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="billing_date" value="Tanggal Penagihan *" />
            <x-date-input id="billing_date" name="billing_date" class="mt-1 block w-full text-sm" required
                          :value="old('billing_date', $billing?->billing_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('billing_date')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="billing_amount" value="Nilai Penagihan (Rp) *" />
            <input id="billing_amount" type="number" name="billing_amount" step="0.01" min="0.01" required x-model="amount"
                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
            <button type="button" class="mt-1 text-xs font-medium text-maroon-900 underline" x-show="term && term.remaining > 0" x-cloak
                    @click="amount = String(term.remaining)">Isi sisa termin</button>
            <p class="mt-1 text-xs font-semibold text-maroon-900" x-show="exceeds" x-cloak role="alert">Nilai penagihan melebihi sisa nilai termin.</p>
            <x-input-error :messages="$errors->get('billing_amount')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="due_date" value="Jatuh Tempo" />
            <x-date-input id="due_date" name="due_date" class="mt-1 block w-full text-sm"
                          :value="old('due_date', $billing?->due_date?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="attachment" value="Lampiran Internal (PDF/JPG/PNG, maks 5 MB)" />
            <input id="attachment" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png"
                   class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
            @if ($billing?->attachment)
                <p class="mt-1 text-xs text-maroon-700">Lampiran saat ini tersimpan. Unggah file baru untuk mengganti.</p>
            @endif
            <p class="mt-1 text-xs text-maroon-700">Catatan internal. Berkas penagihan yang perlu disimpan dapat diunggah ke arsip dokumen.</p>
            <x-input-error :messages="$errors->get('attachment')" class="mt-1" />
        </div>
    </div>

    <div>
        <x-input-label for="description" value="Keterangan" />
        <textarea id="description" name="description" rows="3"
                  class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('description', $billing?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    <p class="text-xs text-maroon-700">Nomor invoice diisi saat penagihan dikirim ke client (SENT).</p>
</div>
