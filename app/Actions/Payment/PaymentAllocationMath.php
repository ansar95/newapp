<?php

namespace App\Actions\Payment;

class PaymentAllocationMath
{
    public const MISMATCH_MESSAGE = 'Total alokasi harus sama dengan nilai pembayaran.';

    public const OVERRUN_REASON_MESSAGE = 'Pembayaran membuat actual cost item melebihi nilai BOQ. Isi alasan kelebihan biaya.';

    /**
     * Money is compared in whole cents to avoid float rounding drift.
     */
    public static function toCents(float|int|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function fromCents(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
