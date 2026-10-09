@props(['context'])

@php
    /** @var \App\Support\ProjectDetailContext $context */
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
    <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Nilai BOQ</p>
        <p class="mt-2 text-xl font-semibold text-maroon-950"><x-money :amount="$context->totalBoqValue" /></p>
    </div>
    <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">COGS</p>
        <p class="mt-2 text-xl font-semibold text-maroon-950">{{ $context->totalCogs }}</p>
        <p class="mt-1 text-xs text-maroon-700">Kelompok biaya/pekerjaan</p>
    </div>
    <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Assets</p>
        <p class="mt-2 text-xl font-semibold text-maroon-950">{{ $context->activeAssets }}</p>
        <p class="mt-1 text-xs text-maroon-700">Sedang digunakan · Riwayat {{ $context->totalAssets }}</p>
    </div>
    <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Payment Keluar</p>
        <p class="mt-2 text-xl font-semibold text-maroon-950"><x-money :amount="$context->totalPaid" /></p>
    </div>
    <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Progress</p>
        <p class="mt-2 text-xl font-semibold text-maroon-950"><x-percent :value="$context->overallProgress" :decimals="1" /></p>
        <x-progress-bar class="mt-3" :percent="$context->overallProgress" />
    </div>
</div>
