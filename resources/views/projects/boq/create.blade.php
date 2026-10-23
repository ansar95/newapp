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
                    Download Template Excel
                </a>
                <label class="inline-flex cursor-pointer items-center rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">
                    Import Excel/CSV
                    <input type="file" accept=".xlsx,.csv" class="hidden" @change="importFile($event)">
                </label>
                <button type="button"
                        class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50"
                        @click="exportFile()">
                    Export Form Excel
                </button>
                <p class="w-full text-xs text-maroon-700">Isi template di Excel lalu import file .xlsx-nya. File CSV (pemisah koma atau titik koma) juga diterima. Kode item dibuat otomatis per COGS.</p>
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
                <p class="mb-3 text-xs text-maroon-700">Kode COGS diisi otomatis saat disimpan (COGS-001, COGS-002, …). Kode item boleh dikosongkan, akan diisi otomatis per COGS (ITEM-001, ITEM-002, …).</p>

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
                            <th class="px-2 py-2">Level 2</th>
                            <th class="px-2 py-2">Level 3</th>
                            <th class="px-2 py-2">Total</th>
                            <th class="px-2 py-2"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <template x-for="(item, ii) in cogs.items" :key="ii">
                            <tr class="border-t border-maroon-100">
                                <td class="px-2 py-2"><input type="text" :name="`cogs[${ci}][items][${ii}][code]`" x-model="item.code" placeholder="Otomatis" class="w-24 rounded-md border-maroon-300 text-sm"></td>
                                <td class="px-2 py-2"><input type="text" :name="`cogs[${ci}][items][${ii}][name]`" x-model="item.name" class="w-full rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" :name="`cogs[${ci}][items][${ii}][quantity]`" x-model.number="item.quantity" class="w-24 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="text" :name="`cogs[${ci}][items][${ii}][unit]`" x-model="item.unit" class="w-20 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" :name="`cogs[${ci}][items][${ii}][material_unit_price]`" x-model.number="item.material_unit_price" class="w-32 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="number" step="0.01" min="0" :name="`cogs[${ci}][items][${ii}][service_unit_price]`" x-model.number="item.service_unit_price" class="w-32 rounded-md border-maroon-300 text-sm" required></td>
                                <td class="px-2 py-2"><input type="text" maxlength="255" :name="`cogs[${ci}][items][${ii}][notes]`" x-model="item.notes" placeholder="Opsional" class="w-48 rounded-md border-maroon-300 text-sm"></td>
                                <td class="px-2 py-2"><input type="text" maxlength="255" :name="`cogs[${ci}][items][${ii}][level_3]`" x-model="item.level_3" placeholder="Opsional" class="w-48 rounded-md border-maroon-300 text-sm"></td>
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
                    items: [{ code: 'ITEM-001', name: '', quantity: 1, unit: 'ls', material_unit_price: 0, service_unit_price: 0, notes: '', level_3: '' }]
                }],
                addCogs() {
                    this.cogsList.push({
                        name: '',
                        description: '',
                        items: [{ code: 'ITEM-001', name: '', quantity: 1, unit: 'ls', material_unit_price: 0, service_unit_price: 0, notes: '', level_3: '' }]
                    });
                },
                removeCogs(ci) { this.cogsList.splice(ci, 1); },
                addItem(ci) {
                    const n = String(this.cogsList[ci].items.length + 1).padStart(3, '0');
                    this.cogsList[ci].items.push({ code: 'ITEM-' + n, name: '', quantity: 1, unit: 'ls', material_unit_price: 0, service_unit_price: 0, notes: '', level_3: '' });
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
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                },
                async importFile(event) {
                    const file = event.target.files?.[0];
                    if (!file) {
                        return;
                    }

                    this.importMessage = 'Membaca file...';
                    const body = new FormData();
                    body.append('file', file);

                    try {
                        const response = await fetch(@js(route('boqs.cogs.parse')), {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                            body
                        });
                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(payload.errors?.file?.[0] || payload.message || 'Gagal membaca file.');
                        }

                        const grouped = {};

                        (payload.rows || []).forEach((row) => {
                            const nameKey = (row.cogs_name || '').toLowerCase();

                            if (!grouped[nameKey]) {
                                grouped[nameKey] = { name: row.cogs_name || '', description: row.cogs_description || '', items: [] };
                            }

                            grouped[nameKey].items.push({
                                code: row.item_code || '',
                                name: row.item_name || '',
                                quantity: Number(row.quantity || 0),
                                unit: row.unit || 'ls',
                                material_unit_price: Number(row.material_price || 0),
                                service_unit_price: Number(row.service_price || 0),
                                notes: row.level_2 || row.item_notes || '',
                                level_3: row.level_3 || ''
                            });
                        });

                        const imported = Object.values(grouped).filter((cogs) => cogs.items.length > 0);
                        if (imported.length === 0) {
                            throw new Error('Tidak ada baris COGS/item yang valid.');
                        }

                        this.cogsList = imported;
                        this.importMessage = 'Import berhasil: ' + imported.length + ' COGS, ' + this.itemCount() + ' item.';
                    } catch (error) {
                        this.importMessage = error.message || 'Gagal membaca file.';
                    } finally {
                        event.target.value = '';
                    }
                },
                async exportFile() {
                    const rows = [];

                    this.cogsList.forEach((cogs) => {
                        cogs.items.forEach((item) => {
                            rows.push({
                                cogs_name: String(cogs.name ?? ''),
                                cogs_description: String(cogs.description ?? ''),
                                item_code: String(item.code ?? ''),
                                item_name: String(item.name ?? ''),
                                item_description: '',
                                quantity: String(item.quantity ?? ''),
                                unit: String(item.unit ?? ''),
                                material_price: String(item.material_unit_price ?? ''),
                                service_price: String(item.service_unit_price ?? ''),
                                level_2: String(item.notes ?? ''),
                                level_3: String(item.level_3 ?? '')
                            });
                        });
                    });

                    try {
                        const response = await fetch(@js(route('boqs.cogs.form-export')), {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken() },
                            body: JSON.stringify({ rows })
                        });

                        if (!response.ok) {
                            throw new Error('Gagal membuat file Excel.');
                        }

                        const url = URL.createObjectURL(await response.blob());
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = 'boq-cogs-form.xlsx';
                        link.click();
                        URL.revokeObjectURL(url);
                    } catch (error) {
                        this.importMessage = error.message || 'Gagal membuat file Excel.';
                    }
                }
            }
        }
    </script>
</x-app-layout>
