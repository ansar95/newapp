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

    public static function date(DateTimeInterface|string|null $value, string $format = 'd/m/Y'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format($format);
        }

        return Carbon::parse($value)->format($format);
    }

    public static function isoDate(DateTimeInterface|string|null $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value)->format('Y-m-d');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            return substr($value, 0, 10);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[2], (int) $matches[1]);
        }

        return '';
    }
}
