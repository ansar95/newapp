@props([
    'label',
    'value',
    'hint' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-maroon-200 bg-white p-5 shadow-sm']) }}>
    <p class="text-xs font-bold uppercase tracking-wide text-maroon-700">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold tracking-tight text-maroon-950 tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs font-medium text-maroon-700">{{ $hint }}</p>
    @endif
    {{ $slot }}
</div>
