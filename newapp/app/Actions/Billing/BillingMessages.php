<?php

namespace App\Actions\Billing;

class BillingMessages
{
    public const TERM_PERCENTAGE_EXCEEDS = 'Total persentase termin melebihi 100%.';

    public const TERM_AMOUNT_EXCEEDS = 'Total nilai termin melebihi nilai kontrak.';

    public const BILLING_EXCEEDS_REMAINING = 'Nilai penagihan melebihi sisa nilai termin.';

    public const PAYMENT_EXCEEDS_OUTSTANDING = 'Nilai pembayaran melebihi outstanding penagihan.';
}
