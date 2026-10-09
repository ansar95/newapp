@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-maroon-600 text-sm font-medium leading-5 text-maroon-800 focus:outline-none focus:border-maroon-600 transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-maroon-800 hover:text-maroon-800 hover:border-maroon-300 focus:outline-none focus:text-maroon-800 focus:border-maroon-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
