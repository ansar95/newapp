<?php

namespace App\Enums;

enum ClientPaymentStatus: string
{
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }
}
