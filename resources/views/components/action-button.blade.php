@props([
    'label',
    'loadingLabel' => 'Processing...',
    'variant' => 'primary',
    'type' => 'submit',
])

@php
    $classes = match ($variant) {
        'danger' => 'bg-maroon-900 hover:bg-maroon-950 focus:ring-maroon-700 text-white',
        'warning' => 'bg-maroon-700 hover:bg-maroon-800 focus:ring-maroon-600 text-white',
        'success' => 'bg-maroon-800 hover:bg-maroon-900 focus:ring-maroon-600 text-white',
        'secondary' => 'border border-maroon-300 bg-white text-maroon-900 hover:bg-maroon-50 focus:ring-maroon-600',
        default => 'bg-maroon-800 hover:bg-maroon-900 focus:ring-maroon-600 text-white',
    };
@endphp

<button
    type="{{ $type }}"
    x-data="{ loading: false }"
    x-on:click="if ($el.form) { $el.form.addEventListener('submit', () => { loading = true }, { once: true }) }"
    x-bind:disabled="loading"
    x-bind:aria-busy="loading"
    {{ $attributes->merge([
        'class' => "inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 {$classes}",
    ]) }}
>
    <span x-show="!loading">{{ $label }}</span>
    <span x-cloak x-show="loading">{{ $loadingLabel }}</span>
</button>
