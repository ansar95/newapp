<x-app-layout>
    <x-slot name="header">{{ $project->code }} — {{ $type->label() }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => $type->label(), 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">
            Alokasi {{ $type->label() }} yang sudah dibayar ke Item BOQ. Actual cost {{ $type->label() }} = total nilai yang dialokasikan.
        </p>
        <x-helper-text class="mt-1" text="Permintaan dibuat dan dibayar melalui tab Pengadaan. Hanya permintaan berstatus Dibayar yang muncul di sini. Alokasi dapat diubah kapan saja selama project ACTIVE." />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <x-stat-card label="Total Dibayar" :value="rupiah($actualCost->paidTotal)" :hint="$actualCost->paidCount.' permintaan dibayar'" />
        <x-stat-card label="Teralokasi (Actual)" :value="rupiah($actualCost->projectActual())" />
        <x-stat-card label="Belum Dialokasikan" :value="rupiah($actualCost->unallocatedAmount())" />
        <x-stat-card label="Dalam Proses" :value="$pendingCount" hint="Draft / Diajukan / Disetujui">
            <a href="{{ route('projects.procurement.index', [$project, 'type' => $type->value]) }}" class="mt-2 inline-block text-xs font-semibold text-maroon-900 underline">Lihat di Pengadaan</a>
        </x-stat-card>
    </div>

    @if ($paidRequests->isEmpty())
        <x-empty-state
            :title="'Belum ada permintaan '.$type->label().' yang dibayar.'"
            description="Buat permintaan di tab Pengadaan. Setelah disetujui dan dibayar, item dapat dialokasikan ke Item BOQ di sini."
            actionLabel="Buka Pengadaan"
            :actionUrl="route('projects.procurement.index', [$project, 'type' => $type->value])"
        />
    @endif

    <div class="space-y-6">
        @foreach ($paidRequests as $procurementRequest)
            <div class="rounded-xl border border-maroon-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-maroon-100 px-4 py-3">
                    <div>
                        <a href="{{ route('procurement-requests.show', $procurementRequest) }}" class="text-base font-semibold text-maroon-950 hover:underline">{{ $procurementRequest->request_number }}</a>
                        <p class="text-xs text-maroon-700">
                            Dibayar <x-date-display :value="$procurementRequest->payment_date" />
                            · {{ $procurementRequest->supplier ?? '-' }}
                            @if ($procurementRequest->invoice_number)
                                · Invoice {{ $procurementRequest->invoice_number }}
                            @endif
                        </p>
                    </div>
                    <x-money :amount="$procurementRequest->paid_total" class="text-base font-bold text-maroon-950" />
                </div>

                <div class="divide-y divide-maroon-100">
                    @foreach ($procurementRequest->items as $requestItem)
                        @php
                            $itemAllocated = $requestItem->allocatedAmount();
                            $useOld = (string) old('allocation_item_id') === (string) $requestItem->id;
                            $existingByItem = $requestItem->allocations->keyBy('boq_item_id');
                            $availableItemIds = [];
                            $rows = [];
                            foreach ($approvedBoqs as $boq) {
                                foreach ($boq->cogs as $cogs) {
                                    foreach ($cogs->items as $boqItem) {
                                        $availableItemIds[] = $boqItem->id;
                                        $existing = $existingByItem->get($boqItem->id);
                                        $oldRow = $useOld ? (old('allocations.'.$boqItem->id) ?? []) : null;
                                        $rows[$boqItem->id] = [
                                            'selected' => $useOld ? ! empty($oldRow['selected']) : $existing !== null,
                                            'amount' => $useOld ? ($oldRow['allocated_amount'] ?? '') : ($existing ? (string) (float) $existing->allocated_amount : ''),
                                            'qty' => $useOld ? ($oldRow['allocated_quantity'] ?? '') : ($existing?->allocated_quantity !== null ? (string) (float) $existing->allocated_quantity : ''),
                                        ];
                                    }
                                }
                            }
                            $orphanAllocations = $requestItem->allocations->reject(fn ($allocation) => in_array($allocation->boq_item_id, $availableItemIds, true));
                        @endphp

                        <div x-data="{
                                 allocating: {{ $useOld ? 'true' : 'false' }},
                                 total: {{ $requestItem->costAmount() }},
                                 rows: @js((object) $rows),
                                 get allocated() {
                                     return Object.values(this.rows).reduce((sum, row) => sum + (row.selected ? (parseFloat(row.amount) || 0) : 0), 0);
                                 },
                                 get remaining() { return this.total - this.allocated; },
                                 get exceeds() { return Math.round(this.allocated * 100) > Math.round(this.total * 100); },
                                 rupiah(value) { return 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
                             }">
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="font-semibold text-maroon-950">{{ $requestItem->item_name }}</p>
                                    <p class="mt-1 text-sm text-maroon-800">
                                        {{ rtrim(rtrim(number_format((float) $requestItem->quantity, 4, ',', '.'), '0'), ',') }} {{ $requestItem->unit }}
                                        x <x-money :amount="$requestItem->paid_unit_price ?? $requestItem->unit_price" />
                                        = <x-money :amount="$requestItem->costAmount()" class="font-semibold text-maroon-950" />
                                    </p>
                                    @if ($requestItem->rental_start)
                                        <p class="mt-1 text-xs text-maroon-700">Sewa <x-date-display :value="$requestItem->rental_start" /> s/d <x-date-display :value="$requestItem->rental_end" /></p>
                                    @endif
                                    @if ($requestItem->notes)
                                        <p class="mt-1 text-xs text-maroon-700">{{ $requestItem->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
                                    <p class="text-xs text-maroon-800">
                                        Teralokasi <x-money :amount="$itemAllocated" class="font-semibold" />
                                        · Sisa <x-money :amount="$requestItem->unallocatedAmount()" class="font-semibold" />
                                    </p>
                                    @if ($canAllocate)
                                        <button type="button" @click="allocating = !allocating"
                                                class="rounded-md border border-maroon-300 px-3 py-1.5 text-xs font-semibold text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600"
                                                x-text="allocating ? 'Tutup Alokasi' : '{{ $requestItem->allocations->isEmpty() ? 'Alokasikan' : 'Ubah Alokasi' }}'">
                                            {{ $requestItem->allocations->isEmpty() ? 'Alokasikan' : 'Ubah Alokasi' }}
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <div class="border-t border-maroon-100 px-4 py-3">
                                @if ($requestItem->allocations->isEmpty())
                                    <p class="text-sm text-maroon-700">Belum dialokasikan.</p>
                                @else
                                    <ul class="divide-y divide-maroon-100 text-sm">
                                        @foreach ($requestItem->allocations as $allocation)
                                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                                <span>
                                                    <span class="font-medium text-maroon-950">{{ $allocation->boqItem?->code }} - {{ $allocation->boqItem?->name }}</span>
                                                    <span class="block text-xs text-maroon-700">
                                                        {{ $allocation->boqItem?->cogs?->boq?->code }} · {{ $allocation->boqItem?->cogs?->name }}
                                                        @if ($allocation->allocated_quantity !== null)
                                                            · Qty {{ rtrim(rtrim(number_format((float) $allocation->allocated_quantity, 4, ',', '.'), '0'), ',') }} {{ $requestItem->unit }}
                                                        @endif
                                                    </span>
                                                </span>
                                                <x-money :amount="$allocation->allocated_amount" class="font-semibold text-maroon-950" />
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            @if ($canAllocate)
                                <form method="POST" action="{{ route('procurement-request-items.allocate', $requestItem) }}"
                                      class="border-t border-maroon-200 bg-maroon-50 p-4" x-show="allocating" x-cloak
                                      @submit="if (exceeds) { $event.preventDefault(); }">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="allocation_item_id" value="{{ $requestItem->id }}">

                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-maroon-950">Alokasikan {{ $requestItem->item_name }} - <x-money :amount="$requestItem->costAmount()" /></p>
                                            <x-helper-text text="Centang Item BOQ yang menerima actual cost lalu isi nilainya. Hanya Item BOQ dari BOQ APPROVED di project ini." />
                                        </div>
                                        <div class="rounded-md bg-white px-3 py-2 text-sm ring-1 ring-maroon-200">
                                            Dialokasikan <span class="font-semibold" x-text="rupiah(allocated)"></span>
                                            · Sisa <span class="font-semibold" x-text="rupiah(remaining)"></span>
                                        </div>
                                    </div>

                                    <p class="mb-3 rounded-md border border-maroon-700 bg-white px-3 py-2 text-sm font-semibold text-maroon-900" x-show="exceeds" x-cloak role="alert">
                                        {{ \App\Actions\Procurement\AllocationMath::EXCEEDS_MESSAGE }}
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
                                                            <p class="text-sm font-semibold text-maroon-950">{{ $cogs->code }} - {{ $cogs->name }}</p>
                                                            <div class="mt-2 space-y-2 pl-2 sm:pl-4">
                                                                @foreach ($cogs->items as $boqItem)
                                                                    <div class="grid gap-2 rounded-md p-2 sm:grid-cols-12 sm:items-center" :class="rows['{{ $boqItem->id }}'].selected ? 'bg-maroon-50 ring-1 ring-maroon-200' : ''">
                                                                        <label class="flex items-start gap-2 sm:col-span-6">
                                                                            <input type="checkbox" name="allocations[{{ $boqItem->id }}][selected]" value="1"
                                                                                   x-model="rows['{{ $boqItem->id }}'].selected"
                                                                                   class="mt-0.5 rounded border-maroon-300 text-maroon-800 focus:ring-maroon-600">
                                                                            <span class="text-sm">
                                                                                <span class="font-medium text-maroon-950">{{ $boqItem->code }} - {{ $boqItem->name }}</span>
                                                                                <span class="block text-xs text-maroon-700">
                                                                                    BOQ <x-money :amount="$boqItem->total_price" />
                                                                                    · Actual saat ini <x-money :amount="$projectActualCost->forItem($boqItem)" />
                                                                                </span>
                                                                            </span>
                                                                        </label>
                                                                        <input type="hidden" name="allocations[{{ $boqItem->id }}][boq_item_id]" value="{{ $boqItem->id }}" :disabled="!rows['{{ $boqItem->id }}'].selected">
                                                                        <div class="sm:col-span-3">
                                                                            <input type="number" step="0.01" min="0.01" placeholder="Nilai (Rp)"
                                                                                   name="allocations[{{ $boqItem->id }}][allocated_amount]"
                                                                                   x-model="rows['{{ $boqItem->id }}'].amount"
                                                                                   :disabled="!rows['{{ $boqItem->id }}'].selected"
                                                                                   :required="rows['{{ $boqItem->id }}'].selected"
                                                                                   aria-label="Nilai alokasi {{ $boqItem->name }}"
                                                                                   class="block w-full rounded-md border-maroon-300 text-sm disabled:bg-maroon-50 disabled:text-maroon-400">
                                                                        </div>
                                                                        <div class="sm:col-span-3">
                                                                            <input type="number" step="0.0001" min="0.0001" placeholder="Qty ({{ $requestItem->unit }}, opsional)"
                                                                                   name="allocations[{{ $boqItem->id }}][allocated_quantity]"
                                                                                   x-model="rows['{{ $boqItem->id }}'].qty"
                                                                                   :disabled="!rows['{{ $boqItem->id }}'].selected"
                                                                                   aria-label="Quantity alokasi {{ $boqItem->name }}"
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
            </div>
        @endforeach
    </div>
</x-app-layout>
