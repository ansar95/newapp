<x-app-layout>
    <x-slot name="header">{{ $purchase->purchase_number }}</x-slot>
    <x-slot name="subheader">{{ $project->code }} — Pembelian BMHP</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'BMHP', 'url' => route('projects.bmhp.index', $project)],
        ['label' => $purchase->purchase_number, 'url' => null],
    ]" />

    @php
        $allocatedTotal = $purchase->allocatedAmount();
        $unallocatedTotal = max(0, (float) $purchase->total_amount - $allocatedTotal);
        $canUpdate = auth()->user()->can('update', $purchase);
    @endphp

    {{-- Header --}}
    <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:p-6"
         x-data="{ showCancel: {{ $errors->has('cancel_reason') ? 'true' : 'false' }} }">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Pembelian BMHP</p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-maroon-950">{{ $purchase->purchase_number }}</h2>
                    <x-status-badge :status="$purchase->status" />
                </div>
                <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-maroon-700">Tanggal:</dt> <dd class="inline font-medium"><x-date-display :value="$purchase->purchase_date" /></dd></div>
                    <div><dt class="inline text-maroon-700">Supplier:</dt> <dd class="inline font-medium">{{ $purchase->supplier }}</dd></div>
                    <div><dt class="inline text-maroon-700">No Invoice:</dt> <dd class="inline font-medium">{{ $purchase->invoice_number ?? '-' }}</dd></div>
                    <div>
                        <dt class="inline text-maroon-700">Bukti:</dt>
                        <dd class="inline font-medium">
                            @if ($purchase->receipt_file)
                                <a href="{{ route('bmhp-purchases.receipt', $purchase) }}" class="text-maroon-900 underline">Download</a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($purchase->notes)
                    <p class="mt-3 text-sm text-maroon-800">{{ $purchase->notes }}</p>
                @endif
                <p class="mt-3 text-xs text-maroon-700">
                    Dibuat oleh {{ $purchase->creator?->name ?? '-' }}
                    @if ($purchase->submitted_at) · Submit {{ $purchase->submitter?->name }} ({{ $purchase->submitted_at->format('d/m/Y H:i') }}) @endif
                    @if ($purchase->confirmed_at) · Konfirmasi {{ $purchase->confirmer?->name }} ({{ $purchase->confirmed_at->format('d/m/Y H:i') }}) @endif
                    @if ($purchase->cancelled_at) · Dibatalkan {{ $purchase->canceller?->name }} ({{ $purchase->cancelled_at->format('d/m/Y H:i') }}) @endif
                </p>
            </div>

            <div class="w-full max-w-sm space-y-3">
                <div class="rounded-lg border border-maroon-200 bg-maroon-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total Pembelian</p>
                    <p class="mt-1 text-2xl font-bold text-maroon-950"><x-money :amount="$purchase->total_amount" /></p>
                    <p class="mt-1 text-xs text-maroon-800">
                        Teralokasi <x-money :amount="$allocatedTotal" class="font-semibold" />
                        · Belum dialokasikan <x-money :amount="$unallocatedTotal" class="font-semibold" />
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('update', $purchase)
                        <a href="{{ route('bmhp-purchases.edit', $purchase) }}"
                           class="inline-flex items-center rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Edit Header</a>
                    @endcan
                    @can('submit', $purchase)
                        <form method="POST" action="{{ route('bmhp-purchases.submit', $purchase) }}"
                              onsubmit="return confirm('Submit pembelian ini? Setelah submit, item dan alokasi tidak dapat diubah.');">
                            @csrf
                            <button type="submit" class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Submit</button>
                        </form>
                    @endcan
                    @can('confirm', $purchase)
                        <form method="POST" action="{{ route('bmhp-purchases.confirm', $purchase) }}"
                              onsubmit="return confirm('Konfirmasi pembelian ini? Nilai alokasi akan masuk ke actual cost.');">
                            @csrf
                            <button type="submit" class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Confirm</button>
                        </form>
                    @endcan
                    @can('returnToDraft', $purchase)
                        <form method="POST" action="{{ route('bmhp-purchases.return-to-draft', $purchase) }}"
                              onsubmit="return confirm('Kembalikan pembelian ke DRAFT untuk diperbaiki?');">
                            @csrf
                            <button type="submit" class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Kembalikan ke Draft</button>
                        </form>
                    @endcan
                    @can('cancel', $purchase)
                        <button type="button" @click="showCancel = !showCancel"
                                class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Batalkan</button>
                    @endcan
                </div>
            </div>
        </div>

        @can('cancel', $purchase)
            <form method="POST" action="{{ route('bmhp-purchases.cancel', $purchase) }}" class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4"
                  x-show="showCancel" x-cloak
                  onsubmit="return confirm('Batalkan pembelian ini? Transaksi CANCELLED tidak dihitung sebagai actual dan tidak dapat diaktifkan kembali.');">
                @csrf
                <x-input-label for="cancel_reason" value="Alasan pembatalan *" />
                <textarea id="cancel_reason" name="cancel_reason" rows="2" required
                          class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600"
                          placeholder="Contoh: salah input harga, akan dibuat transaksi baru.">{{ old('cancel_reason') }}</textarea>
                <x-input-error :messages="$errors->get('cancel_reason')" class="mt-1" />
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Batalkan Pembelian</button>
                </div>
            </form>
        @endcan

        @if ($purchase->cancel_reason)
            <div class="mt-4 rounded-lg border border-maroon-300 bg-white p-3 text-sm text-maroon-900">
                <span class="font-semibold">Alasan pembatalan:</span> {{ $purchase->cancel_reason }}
            </div>
        @endif
    </div>

    @if (! $purchase->isEditable())
        <div class="mb-4 rounded-lg border border-maroon-300 bg-maroon-50 px-4 py-3 text-sm font-medium text-maroon-900">
            Pembelian berstatus {{ $purchase->status->value }}: item dan alokasi terkunci.
            @if ($purchase->status === \App\Enums\BmhpPurchaseStatus::Confirmed)
                Jika ada kesalahan, batalkan transaksi ini lalu buat transaksi baru.
            @endif
        </div>
    @endif

    {{-- Add detail --}}
    @if ($canUpdate)
        <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm"
             x-data="{ quantity: @js(old('quantity', '')), unitPrice: @js(old('unit_price', '')), submitting: false,
                       get total() { return (parseFloat(this.quantity) || 0) * (parseFloat(this.unitPrice) || 0); } }">
            <h3 class="font-semibold text-maroon-950">Tambah Item BMHP</h3>
            <x-helper-text text="Pilih dari Master BMHP. Total dihitung otomatis: quantity × harga satuan." />
            <form method="POST" action="{{ route('bmhp-purchases.details.store', $purchase) }}" class="mt-4 grid gap-4 lg:grid-cols-12 lg:items-end"
                  @submit="submitting = true">
                @csrf
                <div class="lg:col-span-4">
                    <x-input-label for="master_bmhp_id" value="BMHP *" />
                    <select id="master_bmhp_id" name="master_bmhp_id" required class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        <option value="">Pilih BMHP</option>
                        @foreach ($masterBmhps as $masterBmhp)
                            <option value="{{ $masterBmhp->id }}" @selected((string) old('master_bmhp_id') === (string) $masterBmhp->id)>
                                {{ $masterBmhp->code }} — {{ $masterBmhp->name }} ({{ $masterBmhp->unit }})
                            </option>
                        @endforeach
                    </select>
                    @if ($masterBmhps->isEmpty())
                        <p class="mt-1 text-xs text-maroon-900">Belum ada Master BMHP ACTIVE. Tambahkan dulu di menu Master BMHP.</p>
                    @endif
                    <x-input-error :messages="$errors->get('master_bmhp_id')" class="mt-1" />
                </div>
                <div class="lg:col-span-2">
                    <x-input-label for="quantity" value="Quantity *" />
                    <input id="quantity" type="number" step="0.0001" min="0.0001" name="quantity" x-model="quantity" required
                           class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                    <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
                </div>
                <div class="lg:col-span-2">
                    <x-input-label for="unit_price" value="Harga Satuan *" />
                    <input id="unit_price" type="number" step="0.01" min="0" name="unit_price" x-model="unitPrice" required
                           class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                    <x-input-error :messages="$errors->get('unit_price')" class="mt-1" />
                </div>
                <div class="lg:col-span-2">
                    <x-input-label value="Total" />
                    <p class="mt-1 rounded-md bg-maroon-50 px-3 py-2 text-sm font-semibold text-maroon-950"
                       x-text="'Rp ' + total.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 })">Rp 0</p>
                </div>
                <div class="lg:col-span-2">
                    <button type="submit" class="w-full rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900 disabled:opacity-60"
                            :disabled="submitting">+ Tambah</button>
                </div>
                <div class="lg:col-span-12">
                    <x-input-label for="detail_notes" value="Catatan item" />
                    <input id="detail_notes" type="text" name="notes" value="{{ old('notes') }}"
                           class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                </div>
            </form>
        </div>
    @endif

    {{-- Details + allocation --}}
    <h3 class="mb-3 text-base font-semibold text-maroon-950">Detail Item &amp; Alokasi ke Item BOQ</h3>

    @if ($purchase->details->isEmpty())
        <x-empty-state title="Belum ada item BMHP pada pembelian ini." description="Tambahkan item BMHP dari Master BMHP terlebih dahulu." />
    @endif

    <div class="space-y-4">
        @foreach ($purchase->details as $detail)
            @php
                $detailAllocated = $detail->allocatedAmount();
                $useOld = (string) old('allocation_detail_id') === (string) $detail->id;
                $existingByItem = $detail->allocations->keyBy('boq_item_id');
                $availableItemIds = [];
                $rows = [];
                foreach ($approvedBoqs as $boq) {
                    foreach ($boq->cogs as $cogs) {
                        foreach ($cogs->items as $item) {
                            $availableItemIds[] = $item->id;
                            $existing = $existingByItem->get($item->id);
                            $oldRow = $useOld ? (old('allocations.'.$item->id) ?? []) : null;
                            $rows[$item->id] = [
                                'selected' => $useOld ? ! empty($oldRow['selected']) : $existing !== null,
                                'amount' => $useOld ? ($oldRow['allocated_amount'] ?? '') : ($existing ? (string) (float) $existing->allocated_amount : ''),
                                'qty' => $useOld ? ($oldRow['allocated_quantity'] ?? '') : ($existing?->allocated_quantity !== null ? (string) (float) $existing->allocated_quantity : ''),
                            ];
                        }
                    }
                }
                $orphanAllocations = $detail->allocations->reject(fn ($allocation) => in_array($allocation->boq_item_id, $availableItemIds, true));
            @endphp

            <div class="rounded-xl border border-maroon-200 bg-white shadow-sm"
                 x-data="{
                     editing: {{ $errors->any() && (string) old('editing_detail_id') === (string) $detail->id ? 'true' : 'false' }},
                     allocating: {{ $useOld ? 'true' : 'false' }},
                     total: {{ (float) $detail->total }},
                     rows: @js($rows),
                     get allocated() {
                         return Object.values(this.rows).reduce((sum, row) => sum + (row.selected ? (parseFloat(row.amount) || 0) : 0), 0);
                     },
                     get remaining() { return this.total - this.allocated; },
                     get exceeds() { return Math.round(this.allocated * 100) > Math.round(this.total * 100); },
                     rupiah(value) { return 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
                 }">
                <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <p class="font-semibold text-maroon-950">
                            {{ $loop->iteration }}. {{ $detail->item_name }}
                            <span class="text-xs font-normal text-maroon-700">({{ $detail->masterBmhp?->code }})</span>
                        </p>
                        <p class="mt-1 text-sm text-maroon-800">
                            {{ rtrim(rtrim(number_format((float) $detail->quantity, 4, ',', '.'), '0'), ',') }} {{ $detail->unit }}
                            × <x-money :amount="$detail->unit_price" />
                            = <x-money :amount="$detail->total" class="font-semibold text-maroon-950" />
                        </p>
                        @if ($detail->notes)
                            <p class="mt-1 text-xs text-maroon-700">{{ $detail->notes }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                        <p class="text-xs text-maroon-800">
                            Teralokasi <x-money :amount="$detailAllocated" class="font-semibold" />
                            · Sisa <x-money :amount="max(0, (float) $detail->total - $detailAllocated)" class="font-semibold" />
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @if ($canAllocate)
                                <button type="button" @click="allocating = !allocating"
                                        class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-maroon-900"
                                        x-text="allocating ? 'Tutup Alokasi' : 'Atur Alokasi'">Atur Alokasi</button>
                            @endif
                            @if ($canUpdate)
                                <button type="button" @click="editing = !editing"
                                        class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Edit</button>
                                <form method="POST" action="{{ route('bmhp-purchase-details.destroy', $detail) }}"
                                      onsubmit="return confirm('Hapus item ini beserta alokasinya?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Hapus</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                @if ($canUpdate)
                    <form method="POST" action="{{ route('bmhp-purchase-details.update', $detail) }}"
                          class="grid gap-3 border-t border-maroon-100 bg-maroon-50 p-4 sm:grid-cols-4 sm:items-end"
                          x-show="editing" x-cloak>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="editing_detail_id" value="{{ $detail->id }}">
                        <input type="hidden" name="master_bmhp_id" value="{{ $detail->master_bmhp_id }}">
                        <div>
                            <x-input-label value="Quantity *" />
                            <input type="number" step="0.0001" min="0.0001" name="quantity" required value="{{ (float) $detail->quantity }}"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                        </div>
                        <div>
                            <x-input-label value="Harga Satuan *" />
                            <input type="number" step="0.01" min="0" name="unit_price" required value="{{ (float) $detail->unit_price }}"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                        </div>
                        <div>
                            <x-input-label value="Catatan" />
                            <input type="text" name="notes" value="{{ $detail->notes }}" class="mt-1 block w-full rounded-md border-maroon-300 text-sm">
                        </div>
                        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Simpan Item</button>
                    </form>
                @endif

                {{-- Current allocations --}}
                <div class="border-t border-maroon-100 p-4">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-maroon-700">Alokasi ke Item BOQ</p>
                    @if ($detail->allocations->isEmpty())
                        <p class="text-sm text-maroon-700">Belum dialokasikan.</p>
                    @else
                        <ul class="divide-y divide-maroon-100 text-sm">
                            @foreach ($detail->allocations as $allocation)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <span>
                                        <span class="font-medium text-maroon-950">{{ $allocation->boqItem?->code }} — {{ $allocation->boqItem?->name }}</span>
                                        <span class="block text-xs text-maroon-700">
                                            {{ $allocation->boqItem?->cogs?->boq?->code }} · {{ $allocation->boqItem?->cogs?->name }}
                                            @if ($allocation->allocated_quantity !== null)
                                                · Qty {{ rtrim(rtrim(number_format((float) $allocation->allocated_quantity, 4, ',', '.'), '0'), ',') }} {{ $detail->unit }}
                                            @endif
                                        </span>
                                    </span>
                                    <x-money :amount="$allocation->allocated_amount" class="font-semibold text-maroon-950" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{-- Allocation form --}}
                @if ($canAllocate)
                    <form method="POST" action="{{ route('bmhp-purchase-details.allocate', $detail) }}"
                          class="border-t border-maroon-200 bg-maroon-50 p-4" x-show="allocating" x-cloak
                          @submit="if (exceeds) { $event.preventDefault(); }">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="allocation_detail_id" value="{{ $detail->id }}">

                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-maroon-950">Alokasikan {{ $detail->item_name }} — <x-money :amount="$detail->total" /></p>
                                <x-helper-text text="Centang Item BOQ yang menerima actual cost lalu isi nilainya. Hanya Item BOQ dari BOQ APPROVED di project ini." />
                            </div>
                            <div class="rounded-md bg-white px-3 py-2 text-sm ring-1 ring-maroon-200">
                                Dialokasikan <span class="font-semibold" x-text="rupiah(allocated)"></span>
                                · Sisa <span class="font-semibold" x-text="rupiah(remaining)"></span>
                            </div>
                        </div>

                        <p class="mb-3 rounded-md border border-maroon-700 bg-white px-3 py-2 text-sm font-semibold text-maroon-900" x-show="exceeds" x-cloak role="alert">
                            Total alokasi melebihi nilai pembelian.
                        </p>

                        @if ($orphanAllocations->isNotEmpty())
                            <p class="mb-3 rounded-md border border-maroon-300 bg-white px-3 py-2 text-xs text-maroon-900">
                                {{ $orphanAllocations->count() }} alokasi mengarah ke Item BOQ yang BOQ-nya sedang tidak APPROVED (misalnya sedang revisi). Alokasi tersebut akan dihapus jika form ini disimpan.
                            </p>
                        @endif

                        @if ($approvedBoqs->isEmpty())
                            <p class="text-sm text-maroon-800">Project belum memiliki BOQ APPROVED, sehingga belum ada Item BOQ yang bisa menerima alokasi.</p>
                        @else
                            <div class="space-y-4">
                                @foreach ($approvedBoqs as $boq)
                                    <div class="rounded-lg border border-maroon-200 bg-white">
                                        <p class="border-b border-maroon-100 px-3 py-2 text-xs font-bold uppercase tracking-wide text-maroon-800">{{ $boq->code }} · Revision {{ str_pad((string) $boq->revision_number, 2, '0', STR_PAD_LEFT) }}</p>
                                        @foreach ($boq->cogs as $cogs)
                                            <div class="px-3 py-2">
                                                <p class="text-sm font-semibold text-maroon-950">{{ $cogs->code }} — {{ $cogs->name }}</p>
                                                <div class="mt-2 space-y-2 pl-2 sm:pl-4">
                                                    @foreach ($cogs->items as $item)
                                                        <div class="grid gap-2 rounded-md p-2 sm:grid-cols-12 sm:items-center" :class="rows['{{ $item->id }}'].selected ? 'bg-maroon-50 ring-1 ring-maroon-200' : ''">
                                                            <label class="flex items-start gap-2 sm:col-span-6">
                                                                <input type="checkbox" name="allocations[{{ $item->id }}][selected]" value="1"
                                                                       x-model="rows['{{ $item->id }}'].selected"
                                                                       class="mt-0.5 rounded border-maroon-300 text-maroon-800 focus:ring-maroon-600">
                                                                <span class="text-sm">
                                                                    <span class="font-medium text-maroon-950">{{ $item->code }} — {{ $item->name }}</span>
                                                                    <span class="block text-xs text-maroon-700">
                                                                        BOQ <x-money :amount="$item->total_price" />
                                                                        · Actual saat ini (BMHP + Payment) <x-money :amount="$actualCost?->forItem($item) ?? 0" />
                                                                    </span>
                                                                </span>
                                                            </label>
                                                            <input type="hidden" name="allocations[{{ $item->id }}][boq_item_id]" value="{{ $item->id }}" :disabled="!rows['{{ $item->id }}'].selected">
                                                            <div class="sm:col-span-3">
                                                                <input type="number" step="0.01" min="0.01" placeholder="Nilai (Rp)"
                                                                       name="allocations[{{ $item->id }}][allocated_amount]"
                                                                       x-model="rows['{{ $item->id }}'].amount"
                                                                       :disabled="!rows['{{ $item->id }}'].selected"
                                                                       :required="rows['{{ $item->id }}'].selected"
                                                                       aria-label="Nilai alokasi {{ $item->name }}"
                                                                       class="block w-full rounded-md border-maroon-300 text-sm disabled:bg-maroon-50 disabled:text-maroon-400">
                                                            </div>
                                                            <div class="sm:col-span-3">
                                                                <input type="number" step="0.0001" min="0.0001" placeholder="Qty ({{ $detail->unit }}, opsional)"
                                                                       name="allocations[{{ $item->id }}][allocated_quantity]"
                                                                       x-model="rows['{{ $item->id }}'].qty"
                                                                       :disabled="!rows['{{ $item->id }}'].selected"
                                                                       aria-label="Quantity alokasi {{ $item->name }}"
                                                                       class="block w-full rounded-md border-maroon-300 text-sm disabled:bg-maroon-50 disabled:text-maroon-400">
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 flex justify-end">
                                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="exceeds">
                                    Simpan Alokasi
                                </button>
                            </div>
                        @endif
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
