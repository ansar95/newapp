<?php

namespace App\Actions\Bmhp;

class BmhpAllocationMath
{
    public const EXCEEDS_MESSAGE = 'Total alokasi melebihi nilai pembelian.';

    public const EXCEEDS_QUANTITY_MESSAGE = 'Total quantity alokasi melebihi quantity pembelian.';

    /**
     * Money is compared in whole cents to avoid float rounding drift.
     */
    public static function toCents(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * Quantities use four decimal places, matching the decimal(18,4) columns.
     */
    public static function toQuantityUnits(float|int|string $quantity): int
    {
        return (int) round((float) $quantity * 10000);
    }
}
