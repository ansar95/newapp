<x-app-layout>
    <x-slot name="header">Create BOQ — {{ $project->code }}</x-slot>

    <div class="mb-4">
        <a href="{{ route('projects.boq.index', $project) }}" class="text-sm text-maroon-700 hover:underline">&larr; Back to BOQ list</a>
    </div>

    <form method="POST" action="{{ route('projects.boq.store', $project) }}"
          x-data="boqForm()"
          @submit="submitting = true"
          class="space-y-6">
        @csrf

        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="code" value="BOQ Code *" />
                    <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code', $suggestedCode)" required />
                </div>
                <div>
                    <x-input-label for="revision_number" value="Revision Number *" />
                    <x-text-input id="revision_number" name="revision_number" type="number" min="1" class="mt-1 block w-full" :value="old('revision_number', 1)" required />
                </div>
            </div>
            <x-helper-text class="mt-3" text="BOQ adalah daftar biaya project. Tambahkan COGS (kelompok biaya), lalu isi Item Pekerjaan di dalamnya." />
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-maroon-100 pt-4">
                <a href="{{ route('boqs.cogs.template') }}"
                   class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">
                    Download Template CSV
                </a>
                <label class="inline-flex cursor-pointer items-center rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">
                    Import CSV
                    <input type="file" accept=".csv,text/csv" class="hidden" @change="importCsv($event)">
                </label>
                <button type="button"
                        class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50"
                        @click="exportCsv()">
                    Export Form CSV
                </button>
                <p class="w-full text-xs text-maroon-700" x-text="importMessage" x-show="importMessage"></p>
            </div>
        </div>

        <template x-for="(cogs, ci) in cogsList" :key="ci">
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-maroon-950" x-text="'COGS ' + (ci + 1)"></h3>
                    <button type="button" class="text-sm text-maroon-900 hover:underline" @click="removeCogs(ci)" x-show="cogsList.length > 1">Hapus COGS</button>
                </div>
                <p class="mb-3 text-xs text-maroon-700">COGS adalah kelompok biaya/pekerjaan dalam project.</p>
                <div class="mb-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="text-sm text-maroon-700">Name</label>
                        <input type="text" :name="`cogs[${ci}][name]`" x-model="cogs.name" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                    </div>
                    <div>
                        <label class="text-sm text-maroon-700">Description</label>
                        <input type="text" :name="`cogs[${ci}][description]`" x-model="cogs.description" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                    </div>
                </div>
                <p class="mb-3 text-xs text-maroon-700">Kode COGS diisi otomatis saat disimpan (COGS-001, COGS-002, …).</p>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-maroon-50 text-left text-xs uppercase text-maroon-700">
                        <tr>
                            <th class="px-2 py-2">Code</th>
                            <th class="px-2 py-2">Item Name</th>
                            <th class="px-2 py-2">Quantity</th>
                            <th class="px-2 py-2">Unit</th>
                            <th class="px-2 py-2">Harga Material</th>
                            <th class="px-2 py-2">Harga Jasa</th>
                            <th class="px-2 py-2">Total</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <template x-for="(item, ii) in cogs.items" :key="ii">
                            <tr class="border-t border-maroon-100">
                                <td class="px-2 py-2"><input type="text" :name="`cogs[${ci}][items][${ii}][code]`" x-model="item.code" class="w-24 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="text" :name="`cogs[${ci}][items][${ii}][name]`" x-model="item.name" class="w-full rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" :name="`cogs[${ci}][items][${ii}][quantity]`" x-model.number="item.quantity" class="w-24 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="text" :name="`cogs[${ci}][items][${ii}][unit]`" x-model="item.unit" class="w-20 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" :name="`cogs[${ci}][items][${ii}][material_unit_price]`" x-model.number="item.material_unit_price" class="w-32 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" :name="`cogs[${ci}][items][${ii}][service_unit_price]`" x-model.number="item.service_unit_price" class="w-32 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2 whitespace-nowrap font-medium" x-text="formatMoney(itemTotal(item))"></td>
                                <td class="px-2 py-2">
                                    <button type="button" class="text-maroon-900 hover:underline" @click="removeItem(ci, ii)" x-show="cogs.items.length > 1">Hapus</button>
                                </td>
                            </tr>
                        </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <button type="button" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm" @click="addItem(ci)">+ Add Item</button>
                    <div class="text-right">
                        <p class="text-sm font-semibold">COGS Total: <span x-text="formatMoney(cogsTotal(cogs))"></span></p>
                        <p class="text-xs text-maroon-700">
                            Material <span x-text="formatMoney(cogsMaterial(cogs))"></span> · Jasa <span x-text="formatMoney(cogsService(cogs))"></span>
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <button type="button" class="rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium" @click="addCogs()">+ Add COGS</button>
            <div class="flex flex-col items-stretch gap-3 sm:items-end">
                <div class="rounded-lg border border-maroon-200 bg-white px-4 py-3 text-sm">
                    <div class="flex flex-wrap gap-4">
                        <span>COGS: <strong x-text="cogsList.length"></strong></span>
                        <span>Items: <strong x-text="itemCount()"></strong></span>
                        <span>Material: <strong x-text="formatMoney(boqMaterial())"></strong></span>
                        <span>Jasa: <strong x-text="formatMoney(boqService())"></strong></span>
                        <span>TOTAL BOQ: <strong x-text="formatMoney(boqTotal())"></strong></span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Processing...' : 'Save Draft'">
                        Save Draft
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        function boqForm() {
            return {
                submitting: false,
                importMessage: '',
                cogsList: [{
                    name: 'Engineering',
                    description: '',
                    items: [{ code: 'ITEM-001', name: '', quantity: 1, unit: 'ls', material_unit_price: 0, service_unit_price: 0 }]
                }],
                addCogs() {
                    this.cogsList.push({
                        name: '',
                        description: '',
                        items: [{ code: 'ITEM-001', name: '', quantity: 1, unit: 'ls', material_unit_price: 0, service_unit_price: 0 }]
                    });
                },
                removeCogs(ci) { this.cogsList.splice(ci, 1); },
                addItem(ci) {
                    const n = String(this.cogsList[ci].items.length + 1).padStart(3, '0');
                    this.cogsList[ci].items.push({ code: 'ITEM-' + n, name: '', quantity: 1, unit: 'ls', material_unit_price: 0, service_unit_price: 0 });
                },
                removeItem(ci, ii) { this.cogsList[ci].items.splice(ii, 1); },
                itemMaterial(item) { return (Number(item.quantity) || 0) * (Number(item.material_unit_price) || 0); },
                itemService(item) { return (Number(item.quantity) || 0) * (Number(item.service_unit_price) || 0); },
                itemTotal(item) { return this.itemMaterial(item) + this.itemService(item); },
                cogsMaterial(cogs) { return cogs.items.reduce((s, i) => s + this.itemMaterial(i), 0); },
                cogsService(cogs) { return cogs.items.reduce((s, i) => s + this.itemService(i), 0); },
                cogsTotal(cogs) { return cogs.items.reduce((s, i) => s + this.itemTotal(i), 0); },
                boqMaterial() { return this.cogsList.reduce((s, c) => s + this.cogsMaterial(c), 0); },
                boqService() { return this.cogsList.reduce((s, c) => s + this.cogsService(c), 0); },
                boqTotal() { return this.cogsList.reduce((s, c) => s + this.cogsTotal(c), 0); },
                itemCount() { return this.cogsList.reduce((s, c) => s + c.items.length, 0); },
                formatMoney(v) { return 'Rp' + new Intl.NumberFormat('id-ID').format(Math.round(v || 0)); },
                parseCsvLine(line) {
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
                        } else if (char === ',' && !inQuotes) {
                            result.push(current);
                            current = '';
                        } else {
                            current += char;
                        }
                    }
                    result.push(current);
                    return result.map((value) => value.trim());
                },
                importCsv(event) {
                    const file = event.target.files?.[0];
                    if (!file) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = () => {
                        try {
                            const text = String(reader.result || '').replace(/^\uFEFF/, '');
                            const lines = text.split(/\r?\n/).filter((line) => line.trim() !== '');
                            if (lines.length < 2) {
                                throw new Error('File CSV kosong atau tidak berisi data.');
                            }

                            const headers = this.parseCsvLine(lines[0]).map((h) => h.toLowerCase());
                            const required = ['cogs_name', 'item_code', 'item_name', 'quantity', 'unit'];
                            for (const column of required) {
                                if (!headers.includes(column)) {
                                    throw new Error('Kolom wajib hilang: ' + column);
                                }
                            }

                            const index = Object.fromEntries(headers.map((header, i) => [header, i]));
                            const materialColumn = 'material_price' in index ? 'material_price' : 'unit_price';
                            if (!(materialColumn in index)) {
                                throw new Error('Kolom wajib hilang: material_price');
                            }
                            const grouped = {};

                            for (let i = 1; i < lines.length; i++) {
                                const cols = this.parseCsvLine(lines[i]);
                                if (cols.every((value) => value === '')) {
                                    continue;
                                }

                                const cogsName = cols[index.cogs_name] || '';
                                if (!cogsName) {
                                    throw new Error('Baris ' + (i + 1) + ': cogs_name wajib diisi.');
                                }

                                const nameKey = cogsName.toLowerCase();

                                if (!grouped[nameKey]) {
                                    grouped[nameKey] = {
                                        name: cogsName,
                                        description: cols[index.cogs_description] || '',
                                        items: []
                                    };
                                }

                                grouped[nameKey].items.push({
                                    code: cols[index.item_code] || '',
                                    name: cols[index.item_name] || '',
                                    quantity: Number(cols[index.quantity] || 0),
                                    unit: cols[index.unit] || 'ls',
                                    material_unit_price: Number(cols[index[materialColumn]] || 0),
                                    service_unit_price: 'service_price' in index ? Number(cols[index.service_price] || 0) : 0
                                });
                            }

                            const imported = Object.values(grouped).filter((cogs) => cogs.items.length > 0);
                            if (imported.length === 0) {
                                throw new Error('Tidak ada baris COGS/item yang valid.');
                            }

                            this.cogsList = imported;
                            this.importMessage = 'Import berhasil: ' + imported.length + ' COGS, ' + this.itemCount() + ' item.';
                        } catch (error) {
                            this.importMessage = error.message || 'Gagal membaca CSV.';
                        } finally {
                            event.target.value = '';
                        }
                    };
                    reader.readAsText(file);
                },
                exportCsv() {
                    const headers = ['cogs_name', 'cogs_description', 'item_code', 'item_name', 'item_description', 'quantity', 'unit', 'material_price', 'service_price'];
                    const rows = [headers.join(',')];

                    this.cogsList.forEach((cogs) => {
                        cogs.items.forEach((item) => {
                            rows.push([
                                this.csvEscape(cogs.name),
                                this.csvEscape(cogs.description || ''),
                                this.csvEscape(item.code),
                                this.csvEscape(item.name),
                                '',
                                item.quantity,
                                this.csvEscape(item.unit),
                                item.material_unit_price,
                                item.service_unit_price
                            ].join(','));
                        });
                    });

                    const blob = new Blob(['\uFEFF' + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'boq-cogs-form.csv';
                    link.click();
                    URL.revokeObjectURL(url);
                },
                csvEscape(value) {
                    const text = String(value ?? '');
                    if (/[",\n]/.test(text)) {
                        return '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                }
            }
        }
    </script>
</x-app-layout>
