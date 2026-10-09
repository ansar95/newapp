<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class Format
{
    public static function rupiah(float|int|string|null $amount): string
    {
        return 'Rp'.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    public static function percent(float|int|string|null $value, int $decimals = 2): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '').'%';
    }

    public static function date(DateTimeInterface|string|null $value, string $format = 'd F Y'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format($format);
        }

        return Carbon::parse($value)->format($format);
    }
}
