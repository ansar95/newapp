<x-app-layout>
    <x-slot name="header">{{ $procurementRequest->request_number }} — {{ $procurementRequest->type->label() }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Pengadaan', 'url' => route('projects.procurement.index', $project)],
        ['label' => $procurementRequest->request_number, 'url' => null],
    ]" />

    @php
        $type = $procurementRequest->type;
        $isPaid = $procurementRequest->isPaid();
        $allocationRoute = match ($type) {
            \App\Enums\ProcurementType::Bmhp => route('projects.bmhp.index', $project),
            \App\Enums\ProcurementType::Rental => route('projects.rentals.index', $project),
            default => null,
        };
        $allocationTabLabel = $type === \App\Enums\ProcurementType::Rental ? 'Sewa Alat' : 'BMHP';
    @endphp

    <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Permintaan {{ $type->label() }}</p>
                <h2 class="mt-1 text-xl font-bold text-maroon-950">{{ $procurementRequest->request_number }}</h2>
                <p class="mt-1 text-sm text-maroon-700">{{ $project->code }} — {{ $project->name }}</p>
            </div>
            <x-status-badge :status="$procurementRequest->status" />
        </div>

        <dl class="mt-6 grid gap-4 text-sm sm:grid-cols-4">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Tanggal Permintaan</dt>
                <dd class="mt-1 font-medium text-maroon-950"><x-date-display :value="$procurementRequest->request_date" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">{{ $type === \App\Enums\ProcurementType::Rental ? 'Vendor Sewa' : 'Supplier' }}</dt>
                <dd class="mt-1 font-medium text-maroon-950">{{ $procurementRequest->supplier ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Estimasi Total</dt>
                <dd class="mt-1 font-semibold text-maroon-950"><x-money :amount="$procurementRequest->estimated_total" /></dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total Dibayar</dt>
                <dd class="mt-1 font-semibold text-maroon-950">
                    @if ($procurementRequest->paid_total !== null)
                        <x-money :amount="$procurementRequest->paid_total" />
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Dibuat oleh</dt>
                <dd class="mt-1 font-medium text-maroon-950">{{ $procurementRequest->creator?->name ?? '-' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Diajukan</dt>
                <dd class="mt-1 font-medium text-maroon-950">
                    @if ($procurementRequest->submitted_at)
                        {{ $procurementRequest->submitter?->name ?? '-' }} · <x-date-display :value="$procurementRequest->submitted_at" format="d M Y H:i" />
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Disetujui</dt>
                <dd class="mt-1 font-medium text-maroon-950">
                    @if ($procurementRequest->approved_at)
                        {{ $procurementRequest->approver?->name ?? '-' }} · <x-date-display :value="$procurementRequest->approved_at" format="d M Y H:i" />
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Dibayar</dt>
                <dd class="mt-1 font-medium text-maroon-950">
                    @if ($procurementRequest->paid_at)
                        {{ $procurementRequest->payer?->name ?? '-' }} · <x-date-display :value="$procurementRequest->payment_date" />
                    @else
                        -
                    @endif
                </dd>
            </div>
            @if ($isPaid)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">No Invoice</dt>
                    <dd class="mt-1 font-medium text-maroon-950">{{ $procurementRequest->invoice_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Bukti Bayar</dt>
                    <dd class="mt-1 font-medium text-maroon-950">
                        @if ($procurementRequest->payment_proof_file)
                            <a href="{{ route('procurement-requests.payment-proof', $procurementRequest) }}" class="text-maroon-900 underline hover:text-maroon-700">Download</a>
                        @else
                            -
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        @if ($procurementRequest->notes)
            <div class="mt-4 rounded-lg bg-maroon-50 px-4 py-3 text-sm text-maroon-900">
                <span class="font-semibold">Catatan:</span> {{ $procurementRequest->notes }}
            </div>
        @endif

        @if ($procurementRequest->return_note && $procurementRequest->isEditable())
            <div class="mt-4 rounded-lg border border-maroon-300 bg-maroon-100 px-4 py-3 text-sm text-maroon-950" role="alert">
                <span class="font-semibold">Dikembalikan oleh {{ $procurementRequest->returner?->name ?? '-' }}:</span> {{ $procurementRequest->return_note }}
            </div>
        @endif

        @if ($procurementRequest->status === \App\Enums\ProcurementStatus::Cancelled)
            <div class="mt-4 rounded-lg border border-maroon-300 bg-white px-4 py-3 text-sm text-maroon-950">
                <span class="font-semibold">Dibatalkan oleh {{ $procurementRequest->canceller?->name ?? '-' }}:</span> {{ $procurementRequest->cancel_reason ?? '-' }}
            </div>
        @endif

        <div class="mt-6 flex flex-wrap items-start gap-2 border-t border-maroon-100 pt-4">
            @can('update', $procurementRequest)
                <a href="{{ route('procurement-requests.edit', $procurementRequest) }}"
                   class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-bold text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    Edit
                </a>
            @endcan

            @can('submit', $procurementRequest)
                <form method="POST" action="{{ route('procurement-requests.submit', $procurementRequest) }}">
                    @csrf
                    <x-action-button label="Ajukan" />
                </form>
            @endcan

            @can('approve', $procurementRequest)
                <form method="POST" action="{{ route('procurement-requests.approve', $procurementRequest) }}">
                    @csrf
                    <x-action-button label="Setujui" variant="success" />
                </form>
            @endcan

            @can('pay', $procurementRequest)
                <a href="{{ route('procurement-requests.pay', $procurementRequest) }}"
                   class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    Catat Pembayaran
                </a>
            @endcan

            @can('returnToDraft', $procurementRequest)
                <form method="POST" action="{{ route('procurement-requests.return-to-draft', $procurementRequest) }}"
                      x-data="{ open: false }" class="flex flex-wrap items-start gap-2">
                    @csrf
                    <button type="button" x-show="!open" @click="open = true"
                            class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-bold text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                        Kembalikan ke Draft
                    </button>
                    <div x-show="open" x-cloak class="flex flex-wrap items-start gap-2">
                        <textarea name="return_note" rows="2" required placeholder="Alasan pengembalian" aria-label="Alasan pengembalian"
                                  class="w-72 rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('return_note') }}</textarea>
                        <x-action-button label="Kembalikan" variant="warning" />
                        <button type="button" @click="open = false" class="px-2 py-2 text-sm text-maroon-800 hover:underline">Batal</button>
                    </div>
                </form>
            @endcan

            @can('cancel', $procurementRequest)
                <form method="POST" action="{{ route('procurement-requests.cancel', $procurementRequest) }}"
                      x-data="{ open: false }" class="flex flex-wrap items-start gap-2">
                    @csrf
                    <button type="button" x-show="!open" @click="open = true"
                            class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-bold text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                        Batalkan Permintaan
                    </button>
                    <div x-show="open" x-cloak class="flex flex-wrap items-start gap-2">
                        <textarea name="cancel_reason" rows="2" required placeholder="Alasan pembatalan" aria-label="Alasan pembatalan"
                                  class="w-72 rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('cancel_reason') }}</textarea>
                        <x-action-button label="Batalkan" variant="danger" />
                        <button type="button" @click="open = false" class="px-2 py-2 text-sm text-maroon-800 hover:underline">Tutup</button>
                    </div>
                </form>
            @endcan

            @if ($isPaid && $allocationRoute)
                <a href="{{ $allocationRoute }}"
                   class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-bold text-maroon-900 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    Alokasi di tab {{ $allocationTabLabel }}
                </a>
            @endif
        </div>
    </div>

    <div class="mb-6 overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="border-b border-maroon-100 px-4 py-3">
            <h3 class="text-base font-semibold text-maroon-950">Item</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-maroon-200 text-sm">
                <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                <tr>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3 text-right">Qty</th>
                    <th class="px-4 py-3 text-right">Harga Estimasi</th>
                    <th class="px-4 py-3 text-right">Total Estimasi</th>
                    @if ($isPaid)
                        <th class="px-4 py-3 text-right">Harga Final</th>
                        <th class="px-4 py-3 text-right">Total Dibayar</th>
                        @if ($type->isAllocatable())
                            <th class="px-4 py-3 text-right">Teralokasi</th>
                        @endif
                    @endif
                </tr>
                </thead>
                <tbody class="divide-y divide-maroon-100">
                @foreach ($procurementRequest->items as $item)
                    <tr class="align-top">
                        <td class="px-4 py-3">
                            <p class="font-medium text-maroon-950">{{ $item->item_name }}</p>
                            @if ($item->asset_category)
                                <p class="text-xs text-maroon-700">{{ $item->asset_category }}{{ $item->brand ? ' · '.$item->brand : '' }}{{ $item->model ? ' '.$item->model : '' }}</p>
                            @endif
                            @if ($item->rental_start)
                                <p class="text-xs text-maroon-700">Sewa <x-date-display :value="$item->rental_start" /> s/d <x-date-display :value="$item->rental_end" /></p>
                            @endif
                            @if ($item->notes)
                                <p class="text-xs text-maroon-700">{{ $item->notes }}</p>
                            @endif
                            @if ($item->allocations->isNotEmpty())
                                <ul class="mt-2 space-y-0.5 text-xs text-maroon-800">
                                    @foreach ($item->allocations as $allocation)
                                        <li>→ {{ $allocation->boqItem?->name ?? '-' }}: <x-money :amount="$allocation->allocated_amount" /></li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">{{ number_format((float) $item->quantity, 2) }} {{ $item->unit }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->unit_price" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->total" /></td>
                        @if ($isPaid)
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->paid_unit_price" /></td>
                            <td class="px-4 py-3 text-right font-semibold whitespace-nowrap"><x-money :amount="$item->paid_total" /></td>
                            @if ($type->isAllocatable())
                                <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$item->allocatedAmount()" /></td>
                            @endif
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($type === \App\Enums\ProcurementType::Asset && $isPaid)
        @php($assets = $procurementRequest->items->flatMap->assets)
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
            <div class="border-b border-maroon-100 px-4 py-3">
                <h3 class="text-base font-semibold text-maroon-950">Aset Tercatat ({{ $assets->count() }})</h3>
                <p class="text-xs text-maroon-700">Aset dibuat otomatis saat pembayaran dan ditugaskan ke project ini. Pembelian aset tidak dihitung sebagai biaya BOQ.</p>
            </div>
            @if ($assets->isEmpty())
                <p class="px-4 py-6 text-sm text-maroon-700">Tidak ada aset yang tercatat dari permintaan ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-maroon-200 text-sm">
                        <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-maroon-100">
                        @foreach ($assets as $asset)
                            <tr>
                                <td class="px-4 py-3 font-medium text-maroon-950">{{ $asset->code }}</td>
                                <td class="px-4 py-3">{{ $asset->name }}</td>
                                <td class="px-4 py-3">{{ $asset->category }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$asset->status" /></td>
                                <td class="px-4 py-3">
                                    @can('view', $asset)
                                        <a href="{{ route('assets.show', $asset) }}" class="font-medium text-maroon-900 hover:underline">Open</a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
