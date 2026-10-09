<?php

use App\Support\Format;

if (! function_exists('rupiah')) {
    function rupiah(float|int|string|null $amount): string
    {
        return Format::rupiah($amount);
    }
}

if (! function_exists('percent_format')) {
    function percent_format(float|int|string|null $value, int $decimals = 2): string
    {
        return Format::percent($value, $decimals);
    }
}

if (! function_exists('date_id')) {
    function date_id(DateTimeInterface|string|null $value, string $format = 'd F Y'): string
    {
        return Format::date($value, $format);
    }
}
