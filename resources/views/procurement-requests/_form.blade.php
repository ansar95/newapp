@php
    /** @var \App\Models\ProcurementRequest|null $procurementRequest */
    /** @var \App\Enums\ProcurementType $type */
    $isAsset = $type === \App\Enums\ProcurementType::Asset;
    $isRental = $type === \App\Enums\ProcurementType::Rental;
    $blankItem = [
        'item_name' => '',
        'unit' => $isAsset ? 'unit' : ($isRental ? 'hari' : ''),
        'quantity' => '',
        'unit_price' => '',
        'asset_category' => '',
        'brand' => '',
        'model' => '',
        'rental_start' => '',
        'rental_end' => '',
        'notes' => '',
    ];
    $initialItems = old('items', $procurementRequest?->items->map(fn ($item) => [
        'item_name' => $item->item_name,
        'unit' => $item->unit,
        'quantity' => (float) $item->quantity,
        'unit_price' => (float) $item->unit_price,
        'asset_category' => $item->asset_category ?? '',
        'brand' => $item->brand ?? '',
        'model' => $item->model ?? '',
        'rental_start' => $item->rental_start?->format('Y-m-d') ?? '',
        'rental_end' => $item->rental_end?->format('Y-m-d') ?? '',
        'notes' => $item->notes ?? '',
    ])->all() ?? []);
    $initialItems = array_values(array_map(fn ($item) => array_merge($blankItem, (array) $item), $initialItems ?: [$blankItem]));
@endphp

<div class="space-y-6" x-data="procurementItemsForm(@js($blankItem), @js($initialItems))">
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <x-input-label value="Jenis Permintaan" />
            <p class="mt-1 rounded-md bg-maroon-50 px-3 py-2 text-sm font-semibold text-maroon-950">{{ $type->label() }}</p>
        </div>
        <div>
            <x-input-label for="request_date" value="Tanggal Permintaan *" />
            <x-text-input id="request_date" type="date" name="request_date" class="mt-1 block w-full text-sm" required
                          :value="old('request_date', $procurementRequest?->request_date?->format('Y-m-d') ?? now()->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('request_date')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="supplier" :value="$isRental ? 'Vendor Sewa' : 'Supplier'" />
            <x-text-input id="supplier" type="text" name="supplier" class="mt-1 block w-full text-sm" maxlength="255"
                          :value="old('supplier', $procurementRequest?->supplier)" />
            <x-input-error :messages="$errors->get('supplier')" class="mt-1" />
        </div>
    </div>

    <div>
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-maroon-800">Item {{ $type->label() }}</h3>
            <button type="button" @click="addItem()"
                    class="rounded-md border border-maroon-300 px-3 py-1.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600">
                + Tambah Item
            </button>
        </div>
        @if ($isAsset)
            <x-helper-text class="mb-2" text="Setiap 1 unit akan tercatat sebagai 1 aset di Master Asset setelah dibayar, lalu otomatis ditugaskan ke project ini. Quantity aset harus bilangan bulat." />
        @elseif ($isRental)
            <x-helper-text class="mb-2" text="Isi periode sewa per alat. Setelah dibayar, biaya sewa dialokasikan ke Item BOQ melalui tab Sewa Alat." />
        @else
            <x-helper-text class="mb-2" text="Setelah dibayar, BMHP dialokasikan ke Item BOQ melalui tab BMHP." />
            <div class="mb-3 rounded-lg border border-maroon-200 bg-maroon-50 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="downloadTemplate()"
                            class="rounded-md border border-maroon-300 bg-white px-3 py-1.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-100 focus:outline-none focus:ring-2 focus:ring-maroon-600">
                        Download Template CSV
                    </button>
                    <label class="inline-flex cursor-pointer items-center rounded-md bg-maroon-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-900 focus-within:ring-2 focus-within:ring-maroon-600">
                        Import CSV
                        <input type="file" accept=".csv,text/csv" class="sr-only" @change="importCsv($event)">
                    </label>
                </div>
                <p class="mt-2 text-xs text-maroon-700">
                    Kolom: <span class="font-mono">item_name, unit, quantity, unit_price, notes</span> (boleh juga <span class="font-mono">nama_barang, satuan, qty, harga_satuan, keterangan</span>).
                    File Excel cukup disimpan sebagai CSV. Hasil import mengganti daftar item di bawah dan bisa diperiksa dulu sebelum disimpan.
                </p>
                <p class="mt-2 text-xs font-semibold" :class="importFailed ? 'text-red-700' : 'text-maroon-900'"
                   x-text="importMessage" x-show="importMessage" x-cloak role="status"></p>
            </div>
        @endif
        <x-input-error :messages="$errors->get('items')" class="mb-2" />

        <div class="space-y-3">
            <template x-for="(item, index) in items" :key="index">
                <div class="rounded-lg border border-maroon-200 bg-maroon-50/40 p-3">
                    <div class="grid gap-3 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <label class="block text-xs font-semibold text-maroon-800" :for="`item_name_${index}`">{{ $isRental ? 'Nama Alat' : 'Nama Barang' }} *</label>
                            <input type="text" :id="`item_name_${index}`" :name="`items[${index}][item_name]`" x-model="item.item_name" required maxlength="255"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-maroon-800" :for="`unit_${index}`">Satuan *</label>
                            <input type="text" :id="`unit_${index}`" :name="`items[${index}][unit]`" x-model="item.unit" required maxlength="50"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-maroon-800" :for="`quantity_${index}`">Qty *</label>
                            <input type="number" :id="`quantity_${index}`" :name="`items[${index}][quantity]`" x-model="item.quantity" required
                                   @if ($isAsset) step="1" min="1" max="{{ \App\Actions\Procurement\ProcurementRules::MAX_ASSET_UNITS_PER_ITEM }}" @else step="0.0001" min="0.0001" @endif
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-maroon-800" :for="`unit_price_${index}`">Harga Satuan (Rp) *</label>
                            <input type="number" :id="`unit_price_${index}`" :name="`items[${index}][unit_price]`" x-model="item.unit_price" required step="0.01" min="0"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        </div>
                        <div class="flex items-end justify-between gap-2 sm:col-span-2">
                            <div>
                                <p class="text-xs font-semibold text-maroon-800">Total</p>
                                <p class="mt-2 text-sm font-semibold tabular-nums text-maroon-950" x-text="rupiah(lineTotal(item))"></p>
                            </div>
                            <button type="button" @click="removeItem(index)" x-show="items.length > 1"
                                    class="rounded-md px-2 py-1 text-xs font-semibold text-maroon-800 hover:bg-maroon-100 focus:outline-none focus:ring-2 focus:ring-maroon-600"
                                    aria-label="Hapus item">Hapus</button>
                        </div>

                        @if ($isAsset)
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-semibold text-maroon-800" :for="`asset_category_${index}`">Kategori Aset *</label>
                                <select :id="`asset_category_${index}`" :name="`items[${index}][asset_category]`" x-model="item.asset_category" required
                                        class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                                    <option value="">Pilih kategori</option>
                                    @foreach ($assetCategories as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-semibold text-maroon-800" :for="`brand_${index}`">Merek</label>
                                <input type="text" :id="`brand_${index}`" :name="`items[${index}][brand]`" x-model="item.brand" maxlength="255"
                                       class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-semibold text-maroon-800" :for="`model_${index}`">Model</label>
                                <input type="text" :id="`model_${index}`" :name="`items[${index}][model]`" x-model="item.model" maxlength="255"
                                       class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                            </div>
                        @endif

                        @if ($isRental)
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-maroon-800" :for="`rental_start_${index}`">Mulai Sewa *</label>
                                <input type="date" :id="`rental_start_${index}`" :name="`items[${index}][rental_start]`" x-model="item.rental_start" required
                                       class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs font-semibold text-maroon-800" :for="`rental_end_${index}`">Selesai Sewa *</label>
                                <input type="date" :id="`rental_end_${index}`" :name="`items[${index}][rental_end]`" x-model="item.rental_end" required :min="item.rental_start"
                                       class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                            </div>
                        @endif

                        <div class="{{ $isRental ? 'sm:col-span-6' : 'sm:col-span-12' }}">
                            <label class="block text-xs font-semibold text-maroon-800" :for="`item_notes_${index}`">Keterangan</label>
                            <input type="text" :id="`item_notes_${index}`" :name="`items[${index}][notes]`" x-model="item.notes" maxlength="500"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="mt-3 flex justify-end rounded-lg bg-maroon-50 px-4 py-3 text-sm">
            <span class="mr-3 font-semibold text-maroon-800">Estimasi Total</span>
            <span class="font-bold tabular-nums text-maroon-950" x-text="rupiah(grandTotal)"></span>
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Catatan" />
        <textarea id="notes" name="notes" rows="3"
                  class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('notes', $procurementRequest?->notes) }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
    </div>
</div>

<script>
    function procurementItemsForm(blank, initialItems) {
        const columnAliases = {
            item_name: ['item_name', 'nama_barang', 'nama', 'barang'],
            unit: ['unit', 'satuan'],
            quantity: ['quantity', 'qty', 'jumlah'],
            unit_price: ['unit_price', 'harga_satuan', 'harga'],
            notes: ['notes', 'keterangan', 'catatan'],
        };

        return {
            blank,
            items: initialItems,
            importMessage: '',
            importFailed: false,
            addItem() { this.items.push({ ...this.blank }); },
            removeItem(index) { if (this.items.length > 1) { this.items.splice(index, 1); } },
            lineTotal(item) { return (parseFloat(item.quantity) || 0) * (parseFloat(item.unit_price) || 0); },
            get grandTotal() { return this.items.reduce((sum, item) => sum + this.lineTotal(item), 0); },
            rupiah(value) { return 'Rp ' + Number(value).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); },
            downloadTemplate() {
                const rows = [
                    'sep=,',
                    'item_name,unit,quantity,unit_price,notes',
                    'Semen Portland,Zak,50,65000,Merek Tiga Roda',
                    'Gypsum 9mm,Lembar,100,85000,',
                ];
                const url = URL.createObjectURL(new Blob([rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
                const link = document.createElement('a');
                link.href = url;
                link.download = 'template-permintaan-bmhp.csv';
                link.click();
                URL.revokeObjectURL(url);
            },
            importCsv(event) {
                const file = event.target.files?.[0];
                if (!file) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = () => {
                    try {
                        const imported = this.parseItems(String(reader.result || ''));
                        const hasFilledItems = this.items.some((item) => String(item.item_name || '').trim() !== '');
                        if (hasFilledItems && !window.confirm('Ganti ' + this.items.length + ' item yang sudah diisi dengan ' + imported.length + ' item dari file?')) {
                            return;
                        }
                        this.items = imported;
                        this.importFailed = false;
                        this.importMessage = 'Import berhasil: ' + imported.length + ' item dari ' + file.name + '. Periksa lalu klik Simpan.';
                    } catch (error) {
                        this.importFailed = true;
                        this.importMessage = error.message || 'Gagal membaca file CSV.';
                    } finally {
                        event.target.value = '';
                    }
                };
                reader.readAsText(file);
            },
            parseItems(text) {
                let lines = text.replace(/^\uFEFF/, '').split(/\r?\n/).filter((line) => line.trim() !== '');
                let delimiter = null;
                const sepLine = lines[0]?.match(/^"?sep=(.)"?$/i);
                if (sepLine) {
                    delimiter = sepLine[1];
                    lines = lines.slice(1);
                }
                if (lines.length < 2) {
                    throw new Error('File CSV kosong atau tidak berisi data item.');
                }
                delimiter ??= this.detectDelimiter(lines[0]);

                const headers = this.parseLine(lines[0], delimiter).map((header) => header.toLowerCase().replace(/[\s-]+/g, '_'));
                const index = {};
                for (const [column, aliases] of Object.entries(columnAliases)) {
                    const position = headers.findIndex((header) => aliases.includes(header));
                    if (position !== -1) {
                        index[column] = position;
                    } else if (column !== 'notes') {
                        throw new Error('Kolom wajib tidak ditemukan: ' + column + ' (atau ' + aliases.slice(1).join(' / ') + ').');
                    }
                }

                const items = [];
                for (let i = 1; i < lines.length; i++) {
                    const cols = this.parseLine(lines[i], delimiter);
                    if (cols.every((value) => value === '')) {
                        continue;
                    }
                    const row = i + 1 + (sepLine ? 1 : 0);
                    const name = cols[index.item_name] || '';
                    const unit = cols[index.unit] || '';
                    const quantity = this.parseNumber(cols[index.quantity]);
                    const unitPrice = this.parseNumber(cols[index.unit_price]);

                    if (name === '') {
                        throw new Error('Baris ' + row + ': nama barang wajib diisi.');
                    }
                    if (unit === '') {
                        throw new Error('Baris ' + row + ' (' + name + '): satuan wajib diisi.');
                    }
                    if (quantity === null || quantity <= 0) {
                        throw new Error('Baris ' + row + ' (' + name + '): quantity harus angka lebih dari 0.');
                    }
                    if (unitPrice === null || unitPrice < 0) {
                        throw new Error('Baris ' + row + ' (' + name + '): harga satuan harus angka 0 atau lebih.');
                    }

                    items.push({
                        ...this.blank,
                        item_name: name.slice(0, 255),
                        unit: unit.slice(0, 50),
                        quantity,
                        unit_price: unitPrice,
                        notes: index.notes !== undefined ? (cols[index.notes] || '').slice(0, 500) : '',
                    });
                }

                if (items.length === 0) {
                    throw new Error('Tidak ada baris item yang bisa diimport.');
                }

                return items;
            },
            detectDelimiter(headerLine) {
                const counts = [',', ';', '\t'].map((candidate) => [candidate, headerLine.split(candidate).length]);
                return counts.sort((a, b) => b[1] - a[1])[0][0];
            },
            parseLine(line, delimiter) {
                const result = [];
                let current = '';
                let inQuotes = false;
                for (let i = 0; i < line.length; i++) {
                    const char = line[i];
                    if (char === '"') {
                        if (inQuotes && line[i + 1] === '"') {
                            current += '"';
                            i++;
                        } else {
                            inQuotes = !inQuotes;
                        }
                    } else if (char === delimiter && !inQuotes) {
                        result.push(current);
                        current = '';
                    } else {
                        current += char;
                    }
                }
                result.push(current);
                return result.map((value) => value.trim());
            },
            /**
             * Accepts plain numbers and Indonesian formats such as "Rp 65.000" or "1.250,5".
             */
            parseNumber(raw) {
                let value = String(raw ?? '').replace(/rp\.?/i, '').replace(/\s/g, '');
                if (value === '') {
                    return null;
                }
                const hasComma = value.includes(',');
                const hasDot = value.includes('.');
                if (hasComma && hasDot) {
                    value = value.lastIndexOf(',') > value.lastIndexOf('.')
                        ? value.replace(/\./g, '').replace(',', '.')
                        : value.replace(/,/g, '');
                } else if (hasComma) {
                    value = value.replace(',', '.');
                } else if (/^-?\d{1,3}(\.\d{3})+$/.test(value)) {
                    value = value.replace(/\./g, '');
                }
                const number = Number(value);
                return Number.isFinite(number) ? number : null;
            },
        };
    }
</script>
