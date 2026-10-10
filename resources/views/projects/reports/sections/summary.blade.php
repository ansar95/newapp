@php
    $summary = $report->summary();
    $project = $report->project;
@endphp

<div class="space-y-6">
    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Informasi Project</h3>
        <dl class="grid gap-x-6 gap-y-3 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-xs font-semibold uppercase text-maroon-700">Kode / Nama</dt><dd class="font-medium text-maroon-950">{{ $project->code }} — {{ $project->name }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-maroon-700">Client</dt><dd class="font-medium text-maroon-950">{{ $project->client_name }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-maroon-700">Nomor Kontrak</dt><dd class="font-medium text-maroon-950">{{ $project->contract_number ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-maroon-700">Nilai Kontrak</dt><dd class="font-medium text-maroon-950"><x-money :amount="$summary['contract_value']" /></dd></div>
            <div>
                <dt class="text-xs font-semibold uppercase text-maroon-700">Periode Kontrak</dt>
                <dd class="font-medium text-maroon-950"><x-date-display :value="$project->start_date" /> s/d <x-date-display :value="$project->target_end_date" /></dd>
            </div>
            <div><dt class="text-xs font-semibold uppercase text-maroon-700">Status</dt><dd><x-status-badge :status="$project->status" /></dd></div>
        </dl>
    </div>

    @if ($summary['progress'])
        <div>
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-maroon-800">Progress</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card label="Nilai BOQ (Approved)" :value="rupiah($summary['progress']['boq_value'])" />
                <x-stat-card label="Progress" :value="percent_format($summary['progress']['progress'], 1)" />
                <x-stat-card label="Earned Value" :value="rupiah($summary['progress']['earned_value'])" hint="Nilai BOQ × progress item" />
            </div>
        </div>
    @endif

    @if ($summary['cost'])
        <div>
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-maroon-800">Biaya Aktual vs BOQ</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <x-stat-card label="Actual BMHP" :value="rupiah($summary['cost']['bmhp'])" />
                <x-stat-card label="Actual Sewa Alat" :value="rupiah($summary['cost']['rental'])" />
                <x-stat-card label="Actual Payment" :value="rupiah($summary['cost']['payment'])" />
                <x-stat-card label="Total Actual Cost" :value="rupiah($summary['cost']['actual'])"
                             :hint="percent_format($summary['cost']['percentage'], 1).' dari nilai BOQ'" />
                <x-stat-card label="Sisa Budget" :value="rupiah($summary['cost']['variance'])"
                             :hint="$summary['cost']['status']?->label() ?? 'Belum ada BOQ approved'" />
            </div>
        </div>
    @endif

    @if ($summary['payment'] || $summary['procurement'])
        <div>
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-maroon-800">Pembayaran &amp; Pengadaan</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @if ($summary['payment'])
                    <x-stat-card label="Payment Plan" :value="rupiah($summary['payment']['planned'])" />
                    <x-stat-card label="Payment Dibayar" :value="rupiah($summary['payment']['paid'])" />
                @endif
                @if ($summary['procurement'])
                    @foreach (\App\Enums\ProcurementType::cases() as $type)
                        <x-stat-card :label="'Pengadaan '.$type->label().' Dibayar'" :value="rupiah($summary['procurement']['paid'][$type->value])" />
                    @endforeach
                @endif
            </div>
            @if ($summary['procurement'])
                <x-helper-text class="mt-2" :text="$summary['procurement']['waiting'].' permintaan barang masih dalam proses (Draft / Diajukan / Disetujui).'" />
            @endif
        </div>
    @endif

    @if ($summary['billing'])
        <div>
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-maroon-800">Penagihan Client</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card label="Total Ditagih" :value="rupiah($summary['billing']['billed'])"
                             :hint="percent_format($summary['billing']['billed_percentage'], 1).' dari nilai kontrak'" />
                <x-stat-card label="Diterima" :value="rupiah($summary['billing']['received'])" />
                <x-stat-card label="Piutang" :value="rupiah($summary['billing']['outstanding'])" />
            </div>
        </div>
    @endif

    @if ($summary['assets'] || $summary['documents'])
        <div>
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-maroon-800">Aset &amp; Dokumen</h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($summary['assets'])
                    <x-stat-card label="Aset Digunakan" :value="(string) $summary['assets']['in_use']" />
                @endif
                @if ($summary['documents'])
                    <x-stat-card label="Dokumen" :value="(string) $summary['documents']['total']" />
                    <x-stat-card label="Dokumen Menunggu" :value="(string) $summary['documents']['waiting']" hint="Menunggu client atau admin" />
                    <x-stat-card label="Dokumen Approved" :value="(string) $summary['documents']['approved']" />
                @endif
            </div>
        </div>
    @endif
</div>
