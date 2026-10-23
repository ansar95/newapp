<x-app-layout>
    <x-slot name="header">{{ $procurementRequest->request_number }} — Pembayaran</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Pengadaan', 'url' => route('projects.procurement.index', $project)],
        ['label' => $procurementRequest->request_number, 'url' => route('procurement-requests.show', $procurementRequest)],
        ['label' => 'Pembayaran', 'url' => null],
    ]" />

    @php
        $initialPrices = $procurementRequest->items->mapWithKeys(fn ($item) => [
            $item->id => (string) old('items.'.$item->id.'.paid_unit_price', (float) $item->unit_price),
        ]);
        $quantities = $procurementRequest->items->mapWithKeys(fn ($item) => [$item->id => (float) $item->quantity]);
    @endphp

    <div class="mx-auto max-w-5xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm"
         x-data="{
             prices: @js($initialPrices),
             quantities: @js($quantities),
             submitting: false,
             lineTotal(id) { return (parseFloat(this.prices[id]) || 0) * (this.quantities[id] || 0); },
             get grandTotal() { return Object.keys(this.quantities).reduce((sum, id) => sum + this.lineTotal(id), 0); },
             rupiah(value) { return 'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
         }">
        <h2 class="text-lg font-semibold text-maroon-950">Catat Pembayaran {{ $procurementRequest->type->label() }}</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">
            Pembayaran dicatat sekali secara penuh. Isi harga satuan final sesuai invoice.
            @if ($procurementRequest->type === \App\Enums\ProcurementType::Asset)
                Setelah disimpan, setiap unit otomatis tercatat sebagai aset baru dan ditugaskan ke project {{ $project->code }}.
            @elseif ($procurementRequest->type === \App\Enums\ProcurementType::Rental)
                Setelah disimpan, biaya sewa dapat dialokasikan ke Item BOQ melalui tab Sewa Alat.
            @else
                Setelah disimpan, BMHP dapat dialokasikan ke Item BOQ melalui tab BMHP.
            @endif
        </p>

        <form method="POST" action="{{ route('procurement-requests.pay.store', $procurementRequest) }}" enctype="multipart/form-data"
              @submit="submitting = true">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <x-input-label for="payment_date" value="Tanggal Bayar *" />
                    <x-date-input id="payment_date" name="payment_date" class="mt-1 block w-full text-sm" required
                                  :value="old('payment_date', now()->format('Y-m-d'))" />
                    <x-input-error :messages="$errors->get('payment_date')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="invoice_number" value="No Invoice" />
                    <x-text-input id="invoice_number" type="text" name="invoice_number" class="mt-1 block w-full text-sm" maxlength="100"
                                  :value="old('invoice_number')" />
                    <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="payment_proof_file" value="Bukti Bayar (PDF/JPG/PNG, maks 5 MB)" />
                    <input id="payment_proof_file" type="file" name="payment_proof_file" accept=".pdf,.jpg,.jpeg,.png"
                           class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                    <x-input-error :messages="$errors->get('payment_proof_file')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 overflow-x-auto rounded-lg border border-maroon-200">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga Estimasi</th>
                        <th class="px-4 py-3 text-right">Harga Final (Rp) *</th>
                        <th class="px-4 py-3 text-right">Total Dibayar</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($procurementRequest->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-maroon-950">{{ $item->item_name }}</p>
                                @if ($item->asset_category)
                                    <p class="text-xs text-maroon-700">{{ $item->asset_category }}{{ $item->brand ? ' · '.$item->brand : '' }}{{ $item->model ? ' '.$item->model : '' }}</p>
                                @endif
                                @if ($item->rental_start)
                                    <p class="text-xs text-maroon-700"><x-date-display :value="$item->rental_start" /> s/d <x-date-display :value="$item->rental_end" /></p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->unit_price" /></td>
                            <td class="px-4 py-3 text-right">
                                <input type="number" name="items[{{ $item->id }}][paid_unit_price]" step="0.01" min="0" required
                                       x-model="prices[{{ $item->id }}]" aria-label="Harga final {{ $item->item_name }}"
                                       class="w-40 rounded-md border-maroon-300 text-right text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                                <x-input-error :messages="$errors->get('items.'.$item->id.'.paid_unit_price')" class="mt-1" />
                            </td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums whitespace-nowrap" x-text="rupiah(lineTotal({{ $item->id }}))"></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-maroon-50">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-right font-semibold text-maroon-800">Total Dibayar</td>
                        <td class="px-4 py-3 text-right font-bold tabular-nums text-maroon-950" x-text="rupiah(grandTotal)"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('procurement-requests.show', $procurementRequest) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Simpan Pembayaran'">
                    Simpan Pembayaran
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
