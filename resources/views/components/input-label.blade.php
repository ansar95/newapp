@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-bold text-maroon-900']) }}>
    {{ $value ?? $slot }}
</label>
