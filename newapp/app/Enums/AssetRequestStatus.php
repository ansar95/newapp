<?php

namespace App\Enums;

enum AssetRequestStatus: string
{
    case Requested = 'REQUESTED';
    case Approved = 'APPROVED';
    case Purchased = 'PURCHASED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Purchased => 'Purchased',
            self::Cancelled => 'Cancelled',
        };
    }
}
