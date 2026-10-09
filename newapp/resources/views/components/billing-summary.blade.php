@props(['summary', 'progress' => 0.0, 'compact' => false])

@php
    /** @var \App\Support\ProjectBillingSummary $summary */
    $bars = [
        ['label' => 'Progress Fisik', 'value' => (float) $progress],
        ['label' => 'Billed', 'value' => $summary->billedPercentage()],
        ['label' => 'Received', 'value' => $summary->receivedPercentage()],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-maroon-200 bg-white p-5 shadow-sm']) }}>
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 class="text-base font-semibold text-maroon-950">Ringkasan Penagihan Client</h3>
            <x-helper-text class="mt-1" text="Billed = penagihan berstatus SENT. Received = pembayaran client RECEIVED. Outstanding = Billed − Received." />
        </div>
        @if ($compact)
            <a href="{{ route('projects.billing.index', $summary->project) }}" class="text-sm font-medium text-maroon-900 hover:underline">Buka Penagihan →</a>
        @endif
    </div>

    <dl class="mt-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-maroon-700">Nilai Kontrak</dt>
            <dd class="mt-1 text-lg font-bold text-maroon-950 tabular-nums"><x-money :amount="$summary->contractValue()" /></dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-maroon-700">Total Termin</dt>
            <dd class="mt-1 text-lg font-bold text-maroon-950 tabular-nums"><x-money :amount="$summary->totalTermAmount()" /></dd>
            <dd class="text-xs text-maroon-700"><x-percent :value="$summary->totalTermPercentage()" :decimals="1" /> dari kontrak</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-maroon-700">Total Billed</dt>
            <dd class="mt-1 text-lg font-bold text-maroon-950 tabular-nums"><x-money :amount="$summary->totalBilled()" /></dd>
            <dd class="text-xs text-maroon-700"><x-percent :value="$summary->billedPercentage()" :decimals="1" /> dari kontrak</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-maroon-700">Total Received</dt>
            <dd class="mt-1 text-lg font-bold text-maroon-950 tabular-nums"><x-money :amount="$summary->totalReceived()" /></dd>
            <dd class="text-xs text-maroon-700"><x-percent :value="$summary->receivedPercentage()" :decimals="1" /> dari kontrak</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-maroon-700">Outstanding</dt>
            <dd class="mt-1 text-lg font-bold text-maroon-950 tabular-nums"><x-money :amount="$summary->outstanding()" /></dd>
            <dd class="text-xs text-maroon-700">Belum dibayar client</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-maroon-700">Jumlah Penagihan</dt>
            <dd class="mt-1 text-lg font-bold text-maroon-950 tabular-nums">{{ $summary->billingCount() }}</dd>
            <dd class="text-xs text-maroon-700">
                Status SENT
                @if ($summary->inProgressAmount() > 0)
                    · Proses <x-money :amount="$summary->inProgressAmount()" />
                @endif
            </dd>
        </div>
    </dl>

    <div class="mt-5 grid gap-4 sm:grid-cols-3">
        @foreach ($bars as $bar)
            <div>
                <p class="mb-1 text-xs font-semibold text-maroon-800">{{ $bar['label'] }}</p>
                <x-progress-bar :percent="$bar['value']" />
            </div>
        @endforeach
    </div>
</div>
