@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-maroon-300 text-maroon-950 placeholder:text-maroon-400 focus:border-maroon-600 focus:ring-maroon-600 rounded-md shadow-sm']) }}>
