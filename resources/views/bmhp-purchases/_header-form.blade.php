<div class="space-y-4">
    @if ($purchase)
        <div>
            <x-input-label value="No Pembelian" />
            <p class="mt-1 text-sm font-semibold text-maroon-950">{{ $purchase->purchase_number }}</p>
        </div>
    @else
        <x-helper-text text="Nomor pembelian (PB-0001, PB-0002, ...) dibuat otomatis oleh sistem." />
    @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="purchase_date" value="Tanggal Pembelian *" />
            <x-text-input id="purchase_date" name="purchase_date" type="date" class="mt-1 block w-full" required
                          :value="old('purchase_date', $purchase?->purchase_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('purchase_date')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="invoice_number" value="No Invoice" />
            <x-text-input id="invoice_number" name="invoice_number" class="mt-1 block w-full" maxlength="100"
                          :value="old('invoice_number', $purchase?->invoice_number)" placeholder="INV-2026-001" />
            <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
        </div>
    </div>
    <div>
        <x-input-label for="supplier" value="Supplier *" />
        <x-text-input id="supplier" name="supplier" class="mt-1 block w-full" required
                      :value="old('supplier', $purchase?->supplier)" placeholder="PT ABC" />
        <x-input-error :messages="$errors->get('supplier')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="receipt_file" value="Bukti Pembelian (PDF/JPG/PNG, maks 5 MB)" />
        <input id="receipt_file" type="file" name="receipt_file" accept=".pdf,.jpg,.jpeg,.png"
               class="mt-1 block w-full text-sm text-maroon-800 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white" />
        @if ($purchase?->receipt_file)
            <p class="mt-1 text-xs text-maroon-700">
                File saat ini: <a href="{{ route('bmhp-purchases.receipt', $purchase) }}" class="font-medium text-maroon-900 underline">Download</a>
                · Upload file baru untuk mengganti.
            </p>
        @endif
        <x-input-error :messages="$errors->get('receipt_file')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="notes" value="Catatan" />
        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-maroon-300 shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('notes', $purchase?->notes) }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
    </div>
</div>
