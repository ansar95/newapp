@props(['text'])

<p {{ $attributes->merge(['class' => 'text-sm leading-relaxed text-maroon-700']) }}>
    {{ $text }}
</p>
