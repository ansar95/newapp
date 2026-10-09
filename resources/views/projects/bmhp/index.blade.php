<x-app-layout>
    <x-slot name="header">{{ $project->code }} — BMHP</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'BMHP', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">BMHP adalah actual cost pembelian material/consumable di lapangan.</p>
        <x-helper-text class="mt-1" text="Pembelian dialokasikan ke Item BOQ. Hanya transaksi CONFIRMED yang dihitung sebagai actual. BMHP tidak mengubah BOQ, progress, maupun payment." />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Total Pembelian" value="">
            <p class="mt-2 text-2xl font-bold tracking-tight text-maroon-950 tabular-nums"><x-money :amount="$actualCost->confirmedPurchaseTotal" /></p>
            <p class="mt-1 text-xs font-medium text-maroon-700">Transaksi CONFIRMED</p>
        </x-stat-card>
        <x-stat-card label="Actual BMHP" value="">
            <p class="mt-2 text-2xl font-bold tracking-tight text-maroon-950 tabular-nums"><x-money :amount="$actualCost->projectActual()" /></p>
            <p class="mt-1 text-xs font-medium text-maroon-700">
                Teralokasi ke Item BOQ · Belum dialokasikan <x-money :amount="$actualCost->unallocatedAmount()" />
            </p>
        </x-stat-card>
        <x-stat-card label="Jumlah Transaksi" :value="(string) $actualCost->confirmedPurchaseCount"
                     :hint="'CONFIRMED · '.$pendingCount.' menunggu (Draft/Submitted)'" />
        <x-stat-card label="Jumlah Item" :value="(string) $confirmedItemCount" hint="Baris item pada transaksi CONFIRMED" />
    </div>

    <x-cost-control-summary :context="$context" class="mb-6" />

    <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-maroon-100 px-4 py-3">
            <h3 class="text-base font-semibold text-maroon-950">Daftar Pembelian BMHP</h3>
            @can('create', [\App\Models\BmhpPurchase::class, $project])
                <a href="{{ route('projects.bmhp.create', $project) }}"
                   class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                    + New Purchase
                </a>
            @endcan
        </div>

        @if ($project->status !== \App\Enums\ProjectStatus::Active)
            <p class="border-b border-maroon-100 bg-maroon-50 px-4 py-2 text-xs font-medium text-maroon-800">
                Pembelian BMHP hanya dapat dicatat dan diproses saat project berstatus ACTIVE.
            </p>
        @endif

        @if ($purchases->isEmpty())
            <div class="p-2">
                <x-empty-state
                    title="Belum ada pembelian BMHP."
                    description="Catat pembelian material/consumable lalu alokasikan ke Item BOQ untuk melihat actual cost."
                    :action-label="auth()->user()->can('create', [\App\Models\BmhpPurchase::class, $project]) ? '+ New Purchase' : null"
                    :action-url="auth()->user()->can('create', [\App\Models\BmhpPurchase::class, $project]) ? route('projects.bmhp.create', $project) : null"
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">No Pembelian</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($purchases as $purchase)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3 text-maroon-700">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$purchase->purchase_date" /></td>
                            <td class="px-4 py-3 font-medium text-maroon-950">
                                {{ $purchase->purchase_number }}
                                @if ($purchase->invoice_number)
                                    <span class="block text-xs font-normal text-maroon-700">Invoice {{ $purchase->invoice_number }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $purchase->supplier }}</td>
                            <td class="px-4 py-3">{{ $purchase->details_count }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$purchase->total_amount" /></td>
                            <td class="px-4 py-3"><x-status-badge :status="$purchase->status" /></td>
                            <td class="px-4 py-3">
                                <a href="{{ route('bmhp-purchases.show', $purchase) }}" class="font-medium text-maroon-900 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600">Open</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
