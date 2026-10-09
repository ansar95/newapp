<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'PAID';
    case Void = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function countsAsActual(): bool
    {
        return $this === self::Paid;
    }
}
