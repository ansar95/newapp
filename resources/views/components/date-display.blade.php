@props(['value' => null, 'format' => 'd/m/Y'])

<span {{ $attributes }}>{{ date_id($value, $format) }}</span>
