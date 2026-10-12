<x-app-layout>
    <x-slot name="header">{{ $boq->code }}</x-slot>
    <x-slot name="subheader">{{ $project->name }}</x-slot>

    <x-project-header :project="$project" tab="boq" />

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-status-badge :status="$boq->status" />
        <span class="text-sm text-maroon-700">Revision {{ str_pad((string) $boq->revision_number, 2, '0', STR_PAD_LEFT) }}</span>
        <span class="text-sm font-semibold text-maroon-900">TOTAL BOQ: <x-money :amount="$boq->totalAmount()" /></span>
        <span class="text-sm text-maroon-700">Material <x-money :amount="$boq->materialAmount()" /> · Jasa <x-money :amount="$boq->serviceAmount()" /></span>
    </div>

    @if ($boq->status === \App\Enums\BoqStatus::Draft)
        <div class="mb-4 grid gap-3 text-sm sm:grid-cols-3">
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">COGS</p>
                <p class="font-semibold">{{ $boq->cogs->count() }}</p>
            </div>
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">Items</p>
                <p class="font-semibold">{{ $boq->cogs->sum(fn ($c) => $c->items->count()) }}</p>
            </div>
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">Total BOQ</p>
                <p class="font-semibold"><x-money :amount="$boq->totalAmount()" /></p>
            </div>
        </div>
    @endif

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <x-helper-text text="COGS adalah kelompok biaya/pekerjaan. Total COGS berasal dari Item Pekerjaan di dalamnya." />
        <div class="mt-3 flex flex-wrap items-center gap-2">
            @can('view', $boq)
                <a href="{{ route('boqs.cogs.export', $boq) }}"
                   class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">
                    Export Excel
                </a>
            @endcan
            @if ($boq->status === \App\Enums\BoqStatus::Draft)
                @can('update', $boq)
                    <a href="{{ route('boqs.cogs.template') }}"
                       class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">
                        Download Template Excel
                    </a>
                    <form method="POST" action="{{ route('boqs.cogs.import', $boq) }}" enctype="multipart/form-data"
                          class="flex flex-wrap items-center gap-2"
                          x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <input type="file" name="file" accept=".xlsx,.csv" required
                               class="block w-full max-w-xs text-sm text-maroon-700 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-maroon-900">
                        <button type="submit"
                                class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-medium text-white disabled:opacity-60"
                                :disabled="submitting"
                                x-text="submitting ? 'Importing...' : 'Import Excel/CSV'">
                            Import Excel/CSV
                        </button>
                    </form>
                    <x-input-error :messages="$errors->get('file')" class="w-full" />
                @endcan
            @endif
        </div>
        <p class="mt-2 text-xs text-maroon-700">File Excel (.xlsx) atau CSV (pemisah koma/titik koma) dengan kolom: cogs_name, cogs_description, item_name, item_description, quantity, unit, material_price, service_price, item_notes (keterangan, opsional). Kode COGS dan kode item dibuat otomatis (ITEM-001, ITEM-002, … per COGS). Kolom item_code dari file Export tetap dibaca agar item yang sudah ada tidak terduplikasi. File lama dengan kolom unit_price tetap diterima sebagai harga material; kolom item_category diabaikan.</p>
    </div>

    <div class="mb-6 flex flex-wrap gap-2"
         @if ($boq->status === \App\Enums\BoqStatus::Approved)
             x-data="{ showRevision: false, submittingRevision: false }"
         @endif>
        @if ($boq->status === \App\Enums\BoqStatus::Draft)
            @can('submit', $boq)
                <form method="POST" action="{{ route('boqs.submit', $boq) }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <button class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-60" :disabled="submitting"
                            x-text="submitting ? 'Processing...' : 'Submit BOQ'">Submit BOQ</button>
                </form>
            @endcan
        @endif
        @if ($boq->status === \App\Enums\BoqStatus::WaitingApproval)
            @can('review', $boq)
                <a href="{{ route('boqs.review', $boq) }}" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Review & Approve</a>
            @endcan
        @endif
        @if ($boq->status === \App\Enums\BoqStatus::RevisionRequired)
            @can('prepare', [\App\Models\BoqRevision::class, $boq])
                <a href="{{ route('boqs.revision.show', $boq) }}" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white">Open Revision</a>
            @elsecan('viewForBoq', [\App\Models\BoqRevision::class, $boq])
                <a href="{{ route('boqs.revision.show', $boq) }}" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white">View Revision</a>
            @endcan
        @endif
        @if ($boq->status === \App\Enums\BoqStatus::Approved)
            @can('requestRevision', $boq)
                <button type="button" @click="showRevision = true"
                        class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900">
                    Revisi BOQ
                </button>
            @endcan
            @can('viewForProject', [\App\Models\PaymentPlan::class, $project])
                <a href="{{ route('projects.payment.index', $project) }}" class="rounded-md bg-maroon-700 px-4 py-2 text-sm font-medium text-white">Payment Planning</a>
            @endcan
        @endif
        <a href="{{ route('projects.boq.index', $project) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm">Back</a>

        @if ($boq->status === \App\Enums\BoqStatus::Approved)
            @can('requestRevision', $boq)
                <div x-show="showRevision" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-maroon-800/50 p-4"
                     role="dialog" aria-modal="true">
                    <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
                         @keydown.escape.window="showRevision = false">
                        <h3 class="text-lg font-semibold text-maroon-950">Revisi BOQ</h3>
                        <p class="mt-1 text-sm text-maroon-700">
                            Pilih item yang akan direvisi. Setelah perbaikan, BOQ harus di-approve ulang.
                        </p>
                        <form method="POST" action="{{ route('boqs.request-revision', $boq) }}" class="mt-4 space-y-4"
                              @submit="submittingRevision = true">
                            @csrf
                            <div>
                                <x-input-label for="show-reason" value="Alasan Revisi *" />
                                <textarea id="show-reason" name="reason" rows="3" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>{{ old('reason') }}</textarea>
                            </div>
                            <div>
                                <p class="mb-2 text-sm font-medium text-maroon-800">Item yang Direvisi *</p>
                                <div class="max-h-64 space-y-2 overflow-y-auto rounded-md border border-maroon-200 p-3">
                                    @foreach ($boq->cogs as $cogs)
                                        <p class="pt-2 text-xs font-semibold uppercase text-maroon-700 first:pt-0">{{ $cogs->name }}</p>
                                        @foreach ($cogs->items as $item)
                                            <label class="flex items-start gap-2 text-sm">
                                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                                       class="mt-1 rounded border-maroon-300 text-maroon-950 focus:ring-maroon-600">
                                                <span>{{ $item->code }} — {{ $item->name }}</span>
                                            </label>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="rounded-md border border-maroon-300 px-4 py-2 text-sm" @click="showRevision = false">Cancel</button>
                                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                                        :disabled="submittingRevision"
                                        x-text="submittingRevision ? 'Processing...' : 'Mulai Revisi'">Mulai Revisi</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    </div>

    @forelse ($boq->cogs as $cogs)
        <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold uppercase tracking-wide text-maroon-950">{{ $cogs->name }}</h3>
                <p class="text-sm font-medium">
                    Total: <x-money :amount="$cogs->totalAmount()" />
                    <span class="ml-1 text-xs font-normal text-maroon-700">Material <x-money :amount="$cogs->materialAmount()" /> · Jasa <x-money :amount="$cogs->serviceAmount()" /></span>
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs uppercase text-maroon-700">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-3 py-2">Keterangan</th>
                        <th class="px-3 py-2">Qty</th>
                        <th class="px-3 py-2">Unit</th>
                        <th class="px-3 py-2">Harga Satuan</th>
                        <th class="px-3 py-2">Total</th>
                        @if ($actualCost)
                            <th class="px-3 py-2">BMHP</th>
                            <th class="px-3 py-2">Sewa Alat</th>
                            <th class="px-3 py-2">Payment</th>
                            <th class="px-3 py-2">Total Actual</th>
                            <th class="px-3 py-2">Variance</th>
                            <th class="px-3 py-2">Actual Cost</th>
                        @endif
                        <th class="px-3 py-2">Progress</th>
                        @if ($boq->status === \App\Enums\BoqStatus::Draft)
                            @can('update', $boq)
                                <th class="px-3 py-2">Action</th>
                            @endcan
                        @endif
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($cogs->items as $item)
                        <tr>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $item->name }}</div>
                                <div class="text-xs text-maroon-700">{{ $item->code }}</div>
                            </td>
                            <td class="min-w-[10rem] max-w-xs px-3 py-2 text-maroon-800">{{ $item->notes ?? '-' }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td class="px-3 py-2">{{ $item->unit }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <x-money :amount="$item->unit_price" />
                                <div class="text-xs text-maroon-700">M <x-money :amount="$item->material_unit_price" /> · J <x-money :amount="$item->service_unit_price" /></div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-medium"><x-money :amount="$item->total_price" /></span>
                                <div class="text-xs text-maroon-700">M <x-money :amount="$item->materialTotal()" /> · J <x-money :amount="$item->serviceTotal()" /></div>
                            </td>
                            @if ($actualCost)
                                @php
                                    $itemActual = $actualCost->forItem($item);
                                    $itemVariance = \App\Support\ProjectActualCost::variance((float) $item->total_price, $itemActual);
                                @endphp
                                <td class="px-3 py-2 whitespace-nowrap"><x-money :amount="$actualCost->bmhpForItem($item)" /></td>
                                <td class="px-3 py-2 whitespace-nowrap"><x-money :amount="$actualCost->rentalForItem($item)" /></td>
                                <td class="px-3 py-2 whitespace-nowrap"><x-money :amount="$actualCost->paymentForItem($item)" /></td>
                                <td class="px-3 py-2 whitespace-nowrap font-medium"><x-money :amount="$itemActual" /></td>
                                <td @class(['px-3 py-2 whitespace-nowrap', 'font-semibold text-red-700' => $itemVariance < 0])><x-money :amount="$itemVariance" /></td>
                                <td class="px-3 py-2"><x-percent :value="\App\Support\ProjectActualCost::percentage((float) $item->total_price, $itemActual)" /></td>
                            @endif
                            <td class="px-3 py-2"><x-percent :value="$item->progress_percentage" :decimals="1" /></td>
                            @if ($boq->status === \App\Enums\BoqStatus::Draft)
                                @can('update', $boq)
                                    <td class="px-3 py-2">
                                        <details>
                                            <summary class="cursor-pointer text-maroon-800 hover:underline">Edit</summary>
                                            <form method="POST" action="{{ route('boqs.items.update', $item) }}" class="mt-2 grid max-w-md gap-2 rounded border border-maroon-200 p-3"
                                                  x-data="{ submitting: false }" @submit="submitting = true">
                                                @csrf @method('PATCH')
                                                <div>
                                                    <x-input-label value="Item Name *" />
                                                    <input type="text" name="name" value="{{ old('name', $item->name) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                                </div>
                                                <div class="grid grid-cols-2 gap-2">
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
                                                </div>
                                                <div>
                                                    <x-input-label value="Keterangan" />
                                                    <input type="text" name="notes" maxlength="255" value="{{ $item->notes }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                                                </div>
                                                <button class="rounded-md bg-maroon-800 px-3 py-1.5 text-xs text-white disabled:opacity-60" :disabled="submitting"
                                                        x-text="submitting ? 'Processing...' : 'Update'">Update</button>
                                            </form>
                                        </details>
                                    </td>
                                @endcan
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-right text-sm font-semibold">COGS TOTAL: <x-money :amount="$cogs->totalAmount()" /></p>
            <p class="text-right text-xs text-maroon-700">Material <x-money :amount="$cogs->materialAmount()" /> · Jasa <x-money :amount="$cogs->serviceAmount()" /></p>
            @if ($actualCost)
                @php
                    $cogsActual = $actualCost->forCogs($cogs);
                @endphp
                <div class="mt-3 grid gap-3 rounded-lg border border-maroon-200 bg-maroon-50 p-3 text-sm sm:grid-cols-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-maroon-800 sm:col-span-4">Cost Realization COGS</p>
                    <div><span class="text-maroon-700">BOQ</span><p class="font-semibold"><x-money :amount="$cogs->totalAmount()" /></p></div>
                    <div>
                        <span class="text-maroon-700">Actual (BMHP + Sewa Alat + Payment)</span>
                        <p class="font-semibold"><x-money :amount="$cogsActual" /></p>
                        <p class="text-xs text-maroon-700"><x-money :amount="$actualCost->bmhpForCogs($cogs)" /> + <x-money :amount="$actualCost->rentalForCogs($cogs)" /> + <x-money :amount="$actualCost->paymentForCogs($cogs)" /></p>
                    </div>
                    <div><span class="text-maroon-700">Variance</span><p class="font-semibold"><x-money :amount="\App\Support\ProjectActualCost::variance($cogs->totalAmount(), $cogsActual)" /></p></div>
                    <div>
                        <span class="text-maroon-700">Actual Cost / Progress</span>
                        <p class="font-semibold">
                            <x-percent :value="\App\Support\ProjectActualCost::percentage($cogs->totalAmount(), $cogsActual)" />
                            / <x-percent :value="$cogs->progressPercentage()" />
                        </p>
                    </div>
                </div>
            @endif

            @if ($boq->status === \App\Enums\BoqStatus::Draft)
                @can('update', $boq)
                    <form method="POST" action="{{ route('boqs.items.store', $cogs) }}" class="mt-4 grid gap-3 rounded-md border border-dashed border-maroon-300 p-4 sm:grid-cols-8"
                          x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        <div>
                            <x-input-label value="Code" />
                            <input type="text" name="code" value="{{ $cogs->nextItemCode() }}" placeholder="Otomatis" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                        </div>
                        <div class="sm:col-span-3">
                            <x-input-label value="Item Name *" />
                            <input type="text" name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        </div>
                        <div>
                            <x-input-label value="Qty *" />
                            <input type="number" step="0.01" min="0" name="quantity" value="{{ old('quantity') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        </div>
                        <div>
                            <x-input-label value="Unit *" />
                            <input type="text" name="unit" value="{{ old('unit') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        </div>
                        <div>
                            <x-input-label value="Harga Material *" />
                            <input type="number" step="0.01" min="0" name="material_unit_price" value="{{ old('material_unit_price', 0) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        </div>
                        <div>
                            <x-input-label value="Harga Jasa *" />
                            <input type="number" step="0.01" min="0" name="service_unit_price" value="{{ old('service_unit_price', 0) }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        </div>
                        <div class="sm:col-span-7">
                            <x-input-label value="Keterangan" />
                            <input type="text" name="notes" maxlength="255" value="{{ old('notes') }}" placeholder="Opsional, contoh: termasuk aksesoris / merk tertentu"
                                   class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                        </div>
                        <div class="flex items-end">
                            <button class="w-full rounded-md bg-maroon-700 px-3 py-2 text-xs text-white disabled:opacity-60" :disabled="submitting">Add</button>
                        </div>
                    </form>
                @endcan
            @endif
        </div>
    @empty
        <x-empty-state title="Belum ada COGS." description="Tambahkan COGS dan item sebelum submit BOQ." />
    @endforelse

    @if ($boq->status === \App\Enums\BoqStatus::Draft)
        @can('update', $boq)
            <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 font-semibold">Add COGS</h3>
                <form method="POST" action="{{ route('boqs.cogs.store', $boq) }}" class="grid gap-3 sm:grid-cols-3"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <div>
                        <x-input-label value="Name *" />
                        <input type="text" name="name" value="{{ old('name') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                    </div>
                    <div>
                        <x-input-label value="Description" />
                        <input type="text" name="description" value="{{ old('description') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                    </div>
                    <div class="flex items-end">
                        <button class="w-full rounded-md bg-maroon-800 px-3 py-2 text-sm text-white disabled:opacity-60" :disabled="submitting"
                                x-text="submitting ? 'Processing...' : 'Add COGS'">Add COGS</button>
                    </div>
                </form>
                <p class="mt-2 text-xs text-maroon-700">Kode COGS diisi otomatis. Setelah menambah COGS, tambahkan minimal satu item sebelum submit.</p>
            </div>
        @endcan
    @endif

    <div class="rounded-xl border border-maroon-900 bg-maroon-800 p-4 text-white">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium uppercase tracking-wide text-maroon-200">TOTAL BOQ</span>
            <span class="text-xl font-semibold"><x-money :amount="$boq->totalAmount()" /></span>
        </div>
        <div class="mt-2 flex flex-wrap justify-end gap-4 text-sm text-maroon-100">
            <span>Total Material: <strong class="text-white"><x-money :amount="$boq->materialAmount()" /></strong></span>
            <span>Total Jasa: <strong class="text-white"><x-money :amount="$boq->serviceAmount()" /></strong></span>
        </div>
    </div>

    @if ($boq->revisions->isNotEmpty())
        <div class="mt-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 font-semibold text-maroon-950">Revision History</h3>
            @foreach ($boq->revisions->sortByDesc('revision_number') as $revision)
                <div class="flex items-center justify-between border-b border-maroon-100 py-3 last:border-0">
                    <div>
                        <p class="font-medium">REV{{ str_pad((string) $revision->revision_number, 2, '0', STR_PAD_LEFT) }}</p>
                        <p class="text-xs text-maroon-700">{{ $revision->revision_code }} · <x-date-display :value="$revision->created_at" /></p>
                    </div>
                    <x-status-badge :status="$revision->status" />
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
