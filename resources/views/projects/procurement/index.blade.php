<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Pengadaan</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Pengadaan', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    @php
        $canCreate = auth()->user()->can('create', [\App\Models\ProcurementRequest::class, $project]);
    @endphp

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">Permintaan &amp; Pembelian Barang untuk project ini: BMHP, Aset, dan Sewa Alat.</p>
        <x-helper-text class="mt-1" text="Alur: Draft → Diajukan → Disetujui (Approver) → Dibayar (Finance). Setelah dibayar, BMHP dialokasikan di tab BMHP, Sewa Alat di tab Sewa Alat, dan Aset otomatis tercatat di menu Assets." />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ($summaries as $summary)
            <x-stat-card :label="$summary['type']->label()"
                         :value="rupiah($summary['paid_total'])"
                         :hint="$summary['paid_count'].' dibayar · '.$summary['waiting'].' dalam proses'" />
        @endforeach
    </div>

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-maroon-100 px-4 py-3">
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="mr-2 text-base font-semibold text-maroon-950">Daftar Permintaan</h3>
                <a href="{{ route('projects.procurement.index', $project) }}"
                   @class(['rounded-md px-3 py-1 text-xs font-semibold', 'bg-maroon-800 text-white' => $type === null, 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' => $type !== null])>Semua</a>
                @foreach (\App\Enums\ProcurementType::cases() as $filterType)
                    <a href="{{ route('projects.procurement.index', [$project, 'type' => $filterType->value]) }}"
                       @class(['rounded-md px-3 py-1 text-xs font-semibold', 'bg-maroon-800 text-white' => $type === $filterType, 'bg-maroon-50 text-maroon-800 hover:bg-maroon-100' => $type !== $filterType])>{{ $filterType->label() }}</a>
                @endforeach
            </div>
            @if ($canCreate)
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Enums\ProcurementType::cases() as $createType)
                        <a href="{{ route('projects.procurement.create', [$project, 'type' => $createType->value]) }}"
                           class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-3 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                            + Permintaan {{ $createType->label() }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($project->status !== \App\Enums\ProjectStatus::Active)
            <p class="border-b border-maroon-100 bg-maroon-50 px-4 py-2 text-xs font-medium text-maroon-800">
                Permintaan barang hanya dapat dibuat dan diproses saat project berstatus ACTIVE.
            </p>
        @endif

        @if ($requests->isEmpty())
            <div class="p-2">
                <x-empty-state
                    title="Belum ada permintaan."
                    description="Buat permintaan BMHP, Aset, atau Sewa Alat untuk project ini."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">No Permintaan</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Supplier/Vendor</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Estimasi</th>
                        <th class="px-4 py-3 text-right">Dibayar</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($requests as $procurementRequest)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 font-medium text-maroon-950">{{ $procurementRequest->request_number }}</td>
                            <td class="px-4 py-3">{{ $procurementRequest->type->label() }}</td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$procurementRequest->request_date" /></td>
                            <td class="px-4 py-3">{{ $procurementRequest->supplier ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $procurementRequest->items_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$procurementRequest->estimated_total" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($procurementRequest->paid_total !== null)
                                    <x-money :amount="$procurementRequest->paid_total" />
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-status-badge :status="$procurementRequest->status" /></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('procurement-requests.show', $procurementRequest) }}" class="font-medium text-maroon-900 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600">Open</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
