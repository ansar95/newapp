<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidPaymentPlanException extends RuntimeException
{
    public static function notReadyToPay(): self
    {
        return new self('Payment can only be created when the payment plan status is READY_TO_PAY.');
    }

    public static function alreadyPaid(): self
    {
        return new self('Payment plan is already PAID.');
    }
}
