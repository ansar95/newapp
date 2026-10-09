@props(['value' => 0, 'decimals' => 2])

<span {{ $attributes }}>{{ percent_format($value, $decimals) }}</span>
