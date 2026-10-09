@props(['action'])

@php
    /** @var array{key: string, message: string, button: string, url: string|null} $action */
@endphp

<div class="rounded-xl border border-maroon-900 bg-maroon-800 p-5 text-white shadow-sm" role="region" aria-label="Next Action">
    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-200">Next Action</p>
    <p class="mt-2 text-base font-medium leading-relaxed">{{ $action['message'] }}</p>
    @if (! empty($action['url']) && ! empty($action['button']))
        <a href="{{ $action['url'] }}"
           class="mt-4 inline-flex rounded-md bg-white px-4 py-2 text-sm font-semibold text-maroon-950 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-maroon-800">
            {{ $action['button'] }}
        </a>
    @endif
</div>
