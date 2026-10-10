@props(['context'])

@php
    /** @var \App\Support\ProjectDetailContext $context */
    $isOverBoq = $context->actualVariance < 0;
@endphp

<section {{ $attributes->merge(['class' => 'rounded-xl border border-maroon-200 bg-white p-5 shadow-sm']) }} aria-labelledby="cost-control-title">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <h3 id="cost-control-title" class="text-base font-semibold text-maroon-950">Project Cost Control</h3>
            <p class="mt-0.5 text-xs text-maroon-700">BOQ = baseline · Actual = BMHP + Sewa Alat + Payment · Progress = progres fisik. Ketiganya indikator terpisah.</p>
        </div>
        @if ($isOverBoq)
            <span class="rounded-full bg-maroon-800 px-2.5 py-0.5 text-xs font-semibold text-white">Actual melebihi BOQ</span>
        @endif
    </div>

    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">BOQ Value</dt>
            <dd class="mt-1 text-lg font-semibold text-maroon-950"><x-money :amount="$context->totalBoqValue" /></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Actual Cost</dt>
            <dd class="mt-1 text-lg font-semibold text-maroon-950"><x-money :amount="$context->totalActual" /></dd>
            <p class="text-xs text-maroon-700">BMHP <x-money :amount="$context->bmhpActual" /> · Sewa <x-money :amount="$context->rentalActual" /> · Payment <x-money :amount="$context->paymentActual" /></p>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Variance</dt>
            <dd @class(['mt-1 text-lg font-semibold', 'text-maroon-950' => ! $isOverBoq, 'text-red-700' => $isOverBoq])><x-money :amount="$context->actualVariance" /></dd>
            <p class="text-xs text-maroon-700">BOQ − Actual</p>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Actual Cost %</dt>
            <dd class="mt-1 text-lg font-semibold text-maroon-950"><x-percent :value="$context->actualPercentage" /></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Project Progress</dt>
            <dd class="mt-1 text-lg font-semibold text-maroon-950"><x-percent :value="$context->overallProgress" /></dd>
        </div>
    </dl>

    <div class="mt-5 space-y-3">
        <div>
            <div class="mb-1 flex justify-between text-xs font-medium text-maroon-800">
                <span>Progress fisik</span>
                <x-percent :value="$context->overallProgress" :decimals="1" />
            </div>
            <x-progress-bar :percent="$context->overallProgress" :show-label="false" />
        </div>
        <div>
            <div class="mb-1 flex justify-between text-xs font-medium text-maroon-800">
                <span>Actual cost terhadap BOQ</span>
                <x-percent :value="$context->actualPercentage" :decimals="1" />
            </div>
            <x-progress-bar :percent="$context->actualPercentage" :show-label="false" />
        </div>
    </div>
</section>
