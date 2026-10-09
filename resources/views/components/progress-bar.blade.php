@props(['percent' => 0, 'showLabel' => true])

@php
    $percent = max(0, min(100, (float) $percent));
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }} role="progressbar" aria-valuenow="{{ round($percent, 1) }}" aria-valuemin="0" aria-valuemax="100">
    <div class="flex items-center gap-3">
        <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-maroon-100">
            <div class="h-full rounded-full bg-maroon-700 transition-all duration-300" style="width: {{ $percent }}%"></div>
        </div>
        @if ($showLabel)
            <span class="w-16 shrink-0 text-right text-xs font-bold tabular-nums text-maroon-900">{{ number_format($percent, 1) }}%</span>
        @endif
    </div>
</div>
