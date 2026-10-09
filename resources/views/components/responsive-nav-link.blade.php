@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-maroon-600 text-start text-base font-medium text-maroon-800 bg-maroon-50 focus:outline-none focus:text-maroon-800 focus:bg-maroon-50 focus:border-maroon-600 transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-maroon-800 hover:text-maroon-800 hover:bg-maroon-50 hover:border-maroon-300 focus:outline-none focus:text-maroon-800 focus:bg-maroon-50 focus:border-maroon-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
