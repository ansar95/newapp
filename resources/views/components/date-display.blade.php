@props(['value' => null, 'format' => 'd F Y'])

<span {{ $attributes }}>{{ date_id($value, $format) }}</span>
