<?php

namespace App\Enums;

enum ManPowerTransactionStatus: string
{
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Dibayar',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
